<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ auth: { type: Object } });

// State
const vista = ref('mesas'); 
const mesas = ref([]);
const menu = ref([]);
const categorias = ref([]);
const catActiva = ref(null);
const mesaActual = ref(null);
const orden = ref([]);
const loading = ref(false);
const notif = ref(null);
const modalCobro = ref(false);
const pedidoActivo = ref(null);
const metodoPago = ref('Efectivo');
const cobrandoLoad = ref(false);
const kitchenLoad = ref({ nivel: 'Bajo', total_items: 0, tiempo_estimado: 0 });
const waiterNotifications = ref([]);
const alarmasActivas = ref({}); // id_detalle -> timestamp_listo

// Toast system
const toast = (msg, tipo = 'ok') => { 
    notif.value = { msg, tipo }; 
    setTimeout(() => notif.value = null, 4000); 
};

// Audio for Alarms
const playSound = (type = 'ready') => {
    const audio = new Audio(type === 'ready' ? '/sounds/notification.mp3' : '/sounds/alarm.mp3');
    audio.play().catch(e => console.log('Audio blocked by browser'));
};

const refreshData = async () => {
    try {
        const [mRes, kRes, nRes] = await Promise.all([
            axios.get('/api/mesero/mesas'),
            axios.get('/api/mesero/get-kitchen-load'),
            axios.get('/api/mesero/get-notifications')
        ]);
        mesas.value = mRes.data;
        kitchenLoad.value = kRes.data;
        
        // Check for new "Listo" statuses to trigger sound
        nRes.data.forEach(n => {
            const old = waiterNotifications.value.find(prev => prev.id_detalle === n.id_detalle);
            if (n.estado_cocina === 'Listo' && (!old || old.estado_cocina !== 'Listo')) {
                playSound('ready');
                if (!alarmasActivas.value[n.id_detalle]) {
                    alarmasActivas.value[n.id_detalle] = Date.now();
                }
            }
        });
        waiterNotifications.value = nRes.data;
    } catch (e) { console.error('Error refreshing mesero data'); }
};

let pollInterval = null;
let alarmInterval = null;

onMounted(async () => {
    const [menuRes, catsRes] = await Promise.all([
        axios.get('/api/mesero/menu'),
        axios.get('/api/admin/categorias')
    ]);
    menu.value = menuRes.data;
    categorias.value = catsRes.data;
    refreshData();
    pollInterval = setInterval(refreshData, 8000);

    // Alarm checker loop
    alarmInterval = setInterval(() => {
        const now = Date.now();
        Object.keys(alarmasActivas.value).forEach(id => {
            const diff = (now - alarmasActivas.value[id]) / 1000 / 60; // min
            if (diff >= 2) {
                playSound('alarm');
                toast(`🚨 ¡Mesa con orden lista hace más de 2 min!`, 'err');
            }
        });
    }, 15000);
});

onUnmounted(() => {
    clearInterval(pollInterval);
    clearInterval(alarmInterval);
});

const selectMesa = async (m) => {
    mesaActual.value = m;
    if (m.estado === 'Ocupada') {
        const res = await axios.get(`/api/mesero/pedido-activo/${m.id_mesa}`);
        pedidoActivo.value = res.data;
    } else {
        pedidoActivo.value = null;
        orden.value = [];
    }
    vista.value = 'pedido';
};

const menuFiltrado = computed(() => {
    if (!catActiva.value) return menu.value;
    return menu.value.filter(p => p.id_categoria === catActiva.value);
});

const addToOrder = (prod) => {
    const item = orden.value.find(i => i.id_producto === prod.id_producto);
    if (item) { item.cant++; } 
    else { orden.value.push({ id_producto: prod.id_producto, producto: prod, cant: 1, notas: '' }); }
};

const enviarOrden = async () => {
    if (!orden.value.length) return;
    loading.value = true;
    try {
        await axios.post('/api/mesero/order', {
            id_mesa: mesaActual.value.id_mesa,
            items: orden.value.map(i => ({ id_producto: i.id_producto, cantidad: i.cant, notas: i.notas }))
        });
        toast('✅ Enviado a cocina');
        vista.value = 'mesas';
        refreshData();
    } catch (e) {
        toast('⚠️ ' + (e.response?.data?.message || 'Error'), 'err');
    } finally { loading.value = false; }
};

const totalOrden = computed(() => orden.value.reduce((acc, i) => acc + (i.producto.precio * i.cant), 0));

const factGenerada = ref(null);
const procesarCobro = async () => {
    cobrandoLoad.value = true;
    try {
        const res = await axios.post('/api/mesero/cobrar', { 
            id_pedido: pedidoActivo.value.id_pedido, 
            metodo_pago: metodoPago.value
        });
        factGenerada.value = res.data.factura;
        toast('✅ Pago procesado');
        refreshData();
    } catch (e) { toast('❌ Error al cobrar', 'err'); }
    finally { cobrandoLoad.value = false; }
};

const printTicket = () => {
    window.print();
};

const finalizarTodo = () => {
    modalCobro.value = false;
    factGenerada.value = null;
    pedidoActivo.value = null;
    vista.value = 'mesas';
};

const markAsDelivered = (id_detalle) => {
    delete alarmasActivas.value[id_detalle];
    // Aquí podríamos llamar a un endpoint para marcar como entregado si existiera
    toast('Plato entregado ✓');
};

const cerrarTurno = () => router.post(route('logout'));
</script>

<template>
    <Head title="Mesero Dashboard" />
    
    <div class="app-container">
        <!-- Sidebar Notificaciones -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>🔔 Notificaciones</h3>
                <div class="kitchen-badge" :class="kitchenLoad.nivel">
                    Cocina: {{ kitchenLoad.nivel }}
                </div>
            </div>
            
            <div class="notif-list">
                <div v-for="n in waiterNotifications" :key="n.id_detalle" class="notif-item" :class="n.estado_cocina">
                    <div class="notif-info">
                        <strong>Mesa {{ n.id_mesa }}</strong>
                        <span>{{ n.nombre_prod }}</span>
                    </div>
                    <div class="notif-status">
                        <span class="status-pill">{{ n.estado_cocina }}</span>
                        <button v-if="n.estado_cocina === 'Listo'" @click="markAsDelivered(n.id_detalle)" class="btn-check">✓</button>
                    </div>
                </div>
                <p v-if="!waiterNotifications.length" class="empty-msg">No hay órdenes activas</p>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <div class="user-info">
                    <span class="avatar">🤵</span>
                    <div>
                        <h2>¡Hola, {{ auth.user.nombre_completo }}!</h2>
                        <p>Panel de Mesero</p>
                    </div>
                </div>
                <button @click="cerrarTurno" class="btn-logout">Cerrar Sesión</button>
            </header>

            <div v-if="vista === 'mesas'" class="content-body fade-in">
                <div class="section-title">
                    <h3>Selecciona una Mesa</h3>
                    <span>{{ mesas.filter(m=>m.estado==='Libre').length }} Libres / {{ mesas.length }} Total</span>
                </div>
                
                <div class="mesas-grid">
                    <div v-for="m in mesas" :key="m.id_mesa" 
                        class="mesa-card" 
                        :class="m.estado"
                        @click="selectMesa(m)">
                        <div class="mesa-number">{{ m.id_mesa }}</div>
                        <div class="mesa-status">{{ m.estado }}</div>
                        <div class="mesa-cap">Cap. {{ m.capacidad }}</div>
                    </div>
                </div>
            </div>

            <div v-else class="content-body fade-in">
                <button @click="vista='mesas'" class="btn-back">← Volver a Mesas</button>
                
                <div class="order-layout">
                    <!-- Menu Side -->
                    <div class="menu-section">
                        <div class="cat-bar">
                            <button @click="catActiva=null" :class="{active: !catActiva}">Todos</button>
                            <button v-for="c in categorias" :key="c.id_categoria" 
                                @click="catActiva=c.id_categoria"
                                :class="{active: catActiva===c.id_categoria}">
                                {{ c.nombre_cat }}
                            </button>
                        </div>
                        
                        <div class="menu-grid">
                            <div v-for="p in menuFiltrado" :key="p.id_producto" class="product-card" @click="addToOrder(p)">
                                <div class="prod-img" :style="{ backgroundImage: `url(${p.url_imagen || '/img/default-food.png'})` }"></div>
                                <div class="prod-info">
                                    <h4>{{ p.nombre_prod }}</h4>
                                    <span class="price">$ {{ p.precio }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="order-summary">
                        <h3>Comanda Mesa {{ mesaActual.id_mesa }}</h3>
                        
                        <div v-if="mesaActual.estado === 'Ocupada'" class="active-pedido">
                            <div class="status-banner">Pedido en curso: <strong>{{ pedidoActivo?.estado_pedido }}</strong></div>
                            <div class="active-items">
                                <div v-for="d in pedidoActivo?.detalles" :key="d.id_detalle" class="active-item">
                                    <span>{{ d.cantidad }}x {{ d.producto?.nombre_prod }}</span>
                                    <span class="badge" :class="d.estado_cocina">{{ d.estado_cocina }}</span>
                                </div>
                            </div>
                            <button @click="modalCobro=true" class="btn-cobrar">Cobrar Cuenta</button>
                        </div>

                        <div v-else class="new-order">
                            <div class="order-items">
                                <div v-for="item in orden" :key="item.id_producto" class="order-item">
                                    <div class="item-main">
                                        <span>{{ item.cant }}x {{ item.producto.nombre_prod }}</span>
                                        <strong>$ {{ (item.producto.precio * item.cant).toFixed(2) }}</strong>
                                    </div>
                                    <input v-model="item.notas" placeholder="Notas (ej. sin picante)" class="note-input" />
                                </div>
                                <p v-if="!orden.length" class="empty-order">La orden está vacía</p>
                            </div>
                            
                            <div class="order-footer">
                                <div class="total-row">
                                    <span>Total:</span>
                                    <strong>$ {{ totalOrden.toFixed(2) }}</strong>
                                </div>
                                <button @click="enviarOrden" :disabled="!orden.length || loading" class="btn-send">
                                    {{ loading ? 'Enviando...' : 'Enviar a Cocina' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Notification Toast -->
        <div v-if="notif" class="toast" :class="notif.tipo">
            {{ notif.msg }}
        </div>

        <!-- Modal Cobro -->
        <div v-if="modalCobro" class="modal-overlay">
            <div class="modal-content">
                <div v-if="!factGenerada">
                    <h2>Cobrar Mesa {{ mesaActual.id_mesa }}</h2>
                    <p>Total a pagar: <strong class="total-big">$ {{ pedidoActivo?.detalles.reduce((a,b)=>a+(b.cantidad*b.precio_unitario),0).toFixed(2) }}</strong></p>
                    
                    <div class="form-group">
                        <label>Método de Pago</label>
                        <select v-model="metodoPago" class="modern-select">
                            <option value="Efectivo">💵 Efectivo</option>
                            <option value="Tarjeta">💳 Tarjeta</option>
                            <option value="Transferencia">📱 Transferencia</option>
                        </select>
                    </div>

                    <div class="modal-btns">
                        <button @click="modalCobro=false" class="btn-sec">Cancelar</button>
                        <button @click="procesarCobro" :disabled="cobrandoLoad" class="btn-prim">
                            {{ cobrandoLoad ? 'Procesando...' : 'Confirmar Pago' }}
                        </button>
                    </div>
                </div>
                
                <div v-else class="factura-view">
                    <div id="ticket-print" class="ticket">
                        <h1 class="ticket-brand">HA LA FRIDA</h1>
                        <p>Ticket No: {{ factGenerada.numero_factura }}</p>
                        <p>Fecha: {{ new Date().toLocaleString() }}</p>
                        <hr>
                        <div v-for="d in pedidoActivo.detalles" :key="d.id_detalle" class="ticket-row">
                            <span>{{ d.cantidad }}x {{ d.producto.nombre_prod }}</span>
                            <span>$ {{ (d.cantidad * d.precio_unitario).toFixed(2) }}</span>
                        </div>
                        <hr>
                        <div class="ticket-total">
                            <span>TOTAL:</span>
                            <span>$ {{ factGenerada.total }}</span>
                        </div>
                        <p class="ticket-footer">¡Gracias por su visita!</p>
                    </div>
                    <div class="modal-btns no-print">
                        <button @click="printTicket" class="btn-print">🖨️ Imprimir Ticket</button>
                        <button @click="finalizarTodo" class="btn-prim">Finalizar y Salir</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* CSS Moderno, Premium y Responsivo */
:root {
    --primary: #10b981;
    --secondary: #6366f1;
    --dark: #0f172a;
    --light: #f8fafc;
    --danger: #ef4444;
    --warning: #f59e0b;
}

body { margin: 0; font-family: 'Inter', sans-serif; background: #f1f5f9; color: var(--dark); overflow: hidden; }

.app-container { display: flex; height: 100vh; overflow: hidden; }

/* Sidebar */
.sidebar { width: 320px; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; z-index: 10; }
.sidebar-header { padding: 20px; border-bottom: 1px solid #f1f5f9; }
.kitchen-badge { font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 800; display: inline-block; }
.kitchen-badge.Bajo { background: #dcfce7; color: #16a34a; }
.kitchen-badge.Medio { background: #fef3c7; color: #d97706; }
.kitchen-badge.Alto { background: #fee2e2; color: #dc2626; }

.notif-list { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 10px; }
.notif-item { padding: 12px; border-radius: 12px; background: #f8fafc; border-left: 4px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center; }
.notif-item.Recibido { border-color: #94a3b8; }
.notif-item.En.Preparación { border-color: var(--warning); background: #fffbeb; }
.notif-item.Listo { border-color: var(--primary); background: #f0fdf4; animation: pulse 2s infinite; }
.status-pill { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b; }
.btn-check { background: var(--primary); color: #fff; border: none; border-radius: 6px; padding: 4px 8px; cursor: pointer; }

/* Main Content */
.main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background: #f8fafc; }
.top-bar { padding: 20px 40px; background: #fff; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
.user-info { display: flex; gap: 15px; align-items: center; }
.avatar { font-size: 32px; background: #f1f5f9; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }

.mesas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 20px; padding: 40px; }
.mesa-card { background: #fff; padding: 25px; border-radius: 20px; text-align: center; cursor: pointer; transition: all 0.2s; border: 2px solid transparent; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
.mesa-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
.mesa-card.Libre { border-color: #10b98133; }
.mesa-card.Ocupada { background: var(--dark); color: #fff; }
.mesa-number { font-size: 28px; font-weight: 800; }
.mesa-status { font-size: 12px; opacity: 0.7; font-weight: 600; margin: 5px 0; }

/* Order Layout */
.order-layout { display: flex; gap: 20px; padding: 20px; height: calc(100vh - 160px); }
.menu-section { flex: 2; display: flex; flex-direction: column; gap: 15px; }
.cat-bar { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; }
.cat-bar button { padding: 10px 20px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; white-space: nowrap; font-weight: 600; }
.cat-bar button.active { background: var(--dark); color: #fff; border-color: var(--dark); }

.menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; overflow-y: auto; }
.product-card { background: #fff; border-radius: 18px; overflow: hidden; cursor: pointer; transition: 0.2s; border: 1px solid #e2e8f0; }
.product-card:hover { border-color: var(--primary); }
.prod-img { height: 120px; background-size: cover; background-position: center; }
.prod-info { padding: 12px; text-align: center; }
.prod-info h4 { margin: 0; font-size: 14px; }
.price { color: var(--primary); font-weight: 800; font-size: 16px; }

.order-summary { flex: 1; background: #fff; border-radius: 20px; display: flex; flex-direction: column; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.order-items { flex: 1; overflow-y: auto; padding: 20px; }
.order-item { padding-bottom: 15px; border-bottom: 1px solid #f1f5f9; margin-bottom: 15px; }
.item-main { display: flex; justify-content: space-between; margin-bottom: 8px; }
.note-input { width: 100%; border: 1px solid #e2e8f0; padding: 8px; border-radius: 8px; font-size: 12px; }

.order-footer { padding: 20px; border-top: 2px dashed #f1f5f9; }
.total-row { display: flex; justify-content: space-between; font-size: 20px; margin-bottom: 15px; }
.btn-send { width: 100%; background: var(--primary); color: #fff; border: none; padding: 16px; border-radius: 15px; font-size: 16px; font-weight: 800; cursor: pointer; }

/* Modales */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: #fff; border-radius: 30px; padding: 40px; width: 90%; max-width: 450px; }
.total-big { font-size: 32px; color: var(--primary); }
.modern-select { width: 100%; padding: 14px; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 16px; }

/* Ticket Print */
.ticket { padding: 20px; font-family: monospace; }
.ticket-brand { text-align: center; margin-bottom: 10px; font-size: 24px; }
.ticket-row { display: flex; justify-content: space-between; font-size: 14px; margin: 4px 0; }
.ticket-total { display: flex; justify-content: space-between; font-weight: 800; font-size: 18px; margin-top: 10px; }
.ticket-footer { text-align: center; margin-top: 20px; font-size: 12px; }

/* Responsive */
@media (max-width: 1024px) {
    .sidebar { display: none; }
    .order-layout { flex-direction: column; overflow-y: auto; height: auto; }
}

@media (max-width: 600px) {
    .top-bar { padding: 15px 20px; flex-direction: column; gap: 10px; text-align: center; }
    .mesas-grid { padding: 20px; gap: 15px; }
}

.toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); padding: 15px 30px; border-radius: 15px; color: #fff; font-weight: 700; z-index: 2000; animation: slideUp 0.3s; }
.toast.ok { background: var(--primary); }
.toast.err { background: var(--danger); }

@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.02); background: #dcfce7; } 100% { transform: scale(1); } }
@keyframes slideUp { from { transform: translate(-50%, 50px); opacity: 0; } to { transform: translate(-50%, 0); opacity: 1; } }

@media print {
    .no-print { display: none; }
    body * { visibility: hidden; }
    #ticket-print, #ticket-print * { visibility: visible; }
    #ticket-print { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>
