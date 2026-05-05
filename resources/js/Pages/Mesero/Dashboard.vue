<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ auth: { type: Object } });

// State
const vista = ref('mesas'); // 'mesas' | 'pedido'
const mesas = ref([]);
const menu = ref([]);
const categorias = ref([]);
const catActiva = ref(null);
const mesaActual = ref(null);
const orden = ref([]);
const clienteNombre = ref('');
const clienteNit = ref('');
const loading = ref(false);
const notif = ref(null);
const modalCobro = ref(false);
const pedidoActivo = ref(null);
const metodoPago = ref('Efectivo');
const montoRecibido = ref('');
const cobrandoLoad = ref(false);

// Comunicación Tiempo Real
const estadosMesas = ref({}); // { mesaId: { cooking: 0, ready: 0, total: 0 } }
const notificaciones = ref([]); // [{ id, mesa, producto, hora }]
const kitchenLoad = ref({ nivel: 'Bajo', total_items: 0 });
const alarmasActivas = ref({}); // id_detalle -> timestamp
let statusInterval = null;
let alarmInterval = null;

const playBell = () => {
    try {
        const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
        audio.play();
    } catch (e) {}
};

const toast = (msg, tipo='ok') => { notif.value = {msg,tipo}; setTimeout(()=>notif.value=null, 4000); };

const fetchInitialData = async () => {
    const [m, mn, c] = await Promise.all([
        axios.get('/api/mesero/mesas'),
        axios.get('/api/mesero/menu'),
        axios.get('/api/admin/categorias'),
    ]);
    mesas.value = m.data;
    menu.value = mn.data;
    categorias.value = c.data;
    updateRealTimeStatus();
};

const updateRealTimeStatus = async () => {
    try {
        const res = await axios.get('/api/cocina/orders');
        const orders = res.data;
        const newStatus = {};
        const nuevasNotifs = [];
        
        orders.forEach(o => {
            const mId = o.id_mesa;
            if (!newStatus[mId]) newStatus[mId] = { cooking: 0, ready: 0, total: 0, items: [] };
            
            o.detalles.forEach(d => {
                if (d.estado_cocina === 'Recibido' || d.estado_cocina === 'En Preparación') newStatus[mId].cooking++;
                if (d.estado_cocina === 'Listo') {
                    newStatus[mId].ready++;
                    // Si es una nueva notificación de "Listo"
                    const exists = notificaciones.value.find(n => n.id_detalle === d.id_detalle);
                    if (!exists) {
                        nuevasNotifs.push({
                            id_detalle: d.id_detalle,
                            mesa: mId,
                            producto: d.producto.nombre_prod,
                            hora: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                        });
                    }
                }
                newStatus[mId].total += d.cantidad * parseFloat(d.precio_unitario);
                newStatus[mId].items.push(d);
            });
        });

        if (nuevasNotifs.length > 0) {
            notificaciones.value = [...nuevasNotifs, ...notificaciones.value].slice(0, 15);
            playBell();
            toast(`🔔 ${nuevasNotifs.length} nuevo(s) pedido(s) listos`);
            
            // Iniciar alarmas para los nuevos listos
            nuevasNotifs.forEach(n => {
                if (!alarmasActivas.value[n.id_detalle]) {
                    alarmasActivas.value[n.id_detalle] = Date.now();
                }
            });
        }
        
        // Limpiar alarmas de platos que ya no están en "Listo" (porque se entregaron o cobraron)
        const currentReadyIds = orders.flatMap(o => o.detalles.filter(d => d.estado_cocina === 'Listo').map(d => d.id_detalle));
        Object.keys(alarmasActivas.value).forEach(id => {
            if (!currentReadyIds.includes(parseInt(id))) {
                delete alarmasActivas.value[id];
            }
        });

        estadosMesas.value = newStatus;

        // Carga de cocina
        const kRes = await axios.get('/api/mesero/get-kitchen-load');
        kitchenLoad.value = kRes.data;

    } catch(e) {}
};

onMounted(() => {
    fetchInitialData();
    statusInterval = setInterval(() => {
        updateRealTimeStatus();
        if (vista.value === 'mesas') axios.get('/api/mesero/mesas').then(r => mesas.value = r.data);
    }, 4000);

    alarmInterval = setInterval(() => {
        const now = Date.now();
        Object.keys(alarmasActivas.value).forEach(id => {
            if ((now - alarmasActivas.value[id]) > 120000) { // 2 min
                toast(`🚨 Mesa con plato listo hace +2 min`, 'err');
                try { new Audio('https://assets.mixkit.co/active_storage/sfx/951/951-preview.mp3').play(); } catch(e){}
            }
        });
    }, 20000);
});

onUnmounted(() => {
    clearInterval(statusInterval);
    clearInterval(alarmInterval);
});

// Computed
const menuFiltrado = computed(() => catActiva.value ? menu.value.filter(p => p.id_categoria == catActiva.value) : menu.value);
const totalOrden = computed(() => orden.value.reduce((s, i) => s + i.cant * parseFloat(i.producto.precio), 0));
const totalCobro = computed(() => pedidoActivo.value?.detalles?.reduce((s,d) => s + d.cantidad * parseFloat(d.precio_unitario), 0) ?? 0);
const cambio = computed(() => Math.max(0, parseFloat(montoRecibido.value || 0) - totalCobro.value));

const getCatEmoji = (nombre) => {
    const n = nombre.toLowerCase();
    if (n.includes('taco')) return '🌮';
    if (n.includes('bebida')) return '🥤';
    if (n.includes('postre')) return '🍰';
    if (n.includes('entrada')) return '🥗';
    return '🍽️';
};

// Acciones
const seleccionarMesa = async (mesa) => {
    mesaActual.value = mesa;
    if (mesa.estado === 'Ocupada') {
        const res = await axios.get(`/api/mesero/pedido-activo/${mesa.id_mesa}`);
        pedidoActivo.value = res.data;
        clienteNombre.value = pedidoActivo.value?.nombre_cliente || '';
        clienteNit.value = pedidoActivo.value?.nit_cliente || '';
        modalCobro.value = true;
    } else {
        orden.value = [];
        clienteNombre.value = '';
        clienteNit.value = '';
        vista.value = 'pedido';
    }
};

const agregar = (prod) => {
    const item = orden.value.find(i => i.id_producto === prod.id_producto);
    if (item) { item.cant++; } else { orden.value.push({ id_producto: prod.id_producto, producto: prod, cant: 1, notas: '' }); }
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
    } catch (e) {
        toast('⚠️ ' + (e.response?.data?.message || 'Error'), 'warn');
    } finally { loading.value = false; }
};

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
    } catch (e) { toast('❌ Error al cobrar', 'err'); }
    finally { cobrandoLoad.value = false; }
};

const finalizarTodo = () => {
    modalCobro.value = false;
    factGenerada.value = null;
    pedidoActivo.value = null;
    montoRecibido.value = '';
};

const cerrarTurno = () => router.post(route('logout'));
</script>

<template>
    <Head title="Mesero POS — Ha La Frida" />

    <!-- Notificaciones Flotantes -->
    <Transition name="slide">
        <div v-if="notif" style="position:fixed;top:20px;right:20px;z-index:9999;padding:15px 25px;border-radius:12px;color:#fff;font-weight:800;box-shadow:0 10px 30px rgba(0,0,0,0.2);display:flex;align-items:center;gap:10px;"
            :style="notif.tipo==='err'?'background:#ef4444;':'background:#10b981;'">
            <span>{{ notif.msg }}</span>
        </div>
    </Transition>

    <div style="height:100vh;background:#f8fafc;display:flex;flex-direction:column;font-family:'Inter',system-ui,sans-serif;overflow:hidden;">
        
        <!-- Header POS -->
        <header style="background:#fff;border-bottom:1px solid #e2e8f0;height:65px;display:flex;align-items:center;justify-content:space-between;padding:0 25px;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:20px;">
                <button v-if="vista==='pedido'" @click="vista='mesas'" style="background:#f1f5f9;border:none;border-radius:10px;width:40px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:20px;">🏠</button>
                <div v-else style="font-size:24px;">🌮</div>
                <div>
                    <h1 style="font-size:18px;font-weight:900;color:#0f172a;margin:0;">{{ vista==='mesas'?'Panel de Mesas':'Nuevo Pedido' }}</h1>
                    <p style="font-size:11px;color:#64748b;margin:0;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Ha La Frida · Terminal {{ auth.user.id_usuario }}</p>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:15px;">
                    <div style="text-align:right;">
                        <div style="font-weight:900;font-size:18px;color:#0f172a;">{{ auth.user.nombre_completo }}</div>
                        <div style="font-size:12px;color:#64748b;font-weight:700;">MESERO · <span :style="kitchenLoad.nivel === 'Alto' ? 'color:red' : 'color:green'">COCINA: {{ kitchenLoad.nivel }}</span></div>
                    </div>
                <button @click="cerrarTurno" style="background:#fee2e2;border:none;color:#ef4444;width:40px;height:40px;border-radius:10px;cursor:pointer;font-size:18px;">🚪</button>
            </div>
        </header>

        <!-- Main Content -->
        <main style="flex:1;display:flex;overflow:hidden;">
            
            <!-- VISTA: MAPA DE MESAS + PANEL NOTIF -->
            <div v-if="vista==='mesas'" style="flex:1;display:flex;overflow:hidden;">
                
                <!-- Mapa de Mesas -->
                <div style="flex:1;padding:30px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));gap:25px;align-content:start;">
                    <div v-for="mesa in mesas" :key="mesa.id_mesa" @click="seleccionarMesa(mesa)"
                        style="aspect-ratio:1;border-radius:24px;background:#fff;border:2px solid #e2e8f0;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;transition:all 0.2s;position:relative;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);"
                        :style="mesa.estado==='Ocupada' ? 'border-color:#ef4444;background:#fff5f5;' : 'border-color:#10b981;background:#f0fdf4;'"
                        onmouseover="this.style.transform='translateY(-5px)';this.style.boxShadow='0 20px 25px -5px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)'">
                        
                        <!-- Indicadores Rápidos -->
                        <div v-if="estadosMesas[mesa.id_mesa]" style="position:absolute;top:15px;right:15px;display:flex;flex-direction:column;gap:5px;align-items:flex-end;">
                            <div v-if="estadosMesas[mesa.id_mesa].ready > 0" style="background:#10b981;color:#fff;font-size:10px;font-weight:900;padding:4px 8px;border-radius:8px;animation:pulse 1s infinite;">
                                🔔 {{ estadosMesas[mesa.id_mesa].ready }} LISTO
                            </div>
                        </div>

                        <span style="font-size:40px;margin-bottom:10px;">{{ mesa.estado==='Ocupada'?'👨‍👩‍👧‍👦':'🪑' }}</span>
                        <h3 style="margin:0;font-size:24px;font-weight:900;color:#0f172a;">{{ mesa.id_mesa }}</h3>
                        <p style="margin:5px 0 0 0;font-size:12px;font-weight:700;color:#64748b;">{{ mesa.capacidad }} PAX</p>
                        
                        <div v-if="mesa.estado==='Ocupada'" style="margin-top:15px;background:#ef4444;color:#fff;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:800;">
                            ${{ estadosMesas[mesa.id_mesa]?.total.toFixed(2) || '0.00' }}
                        </div>
                    </div>
                </div>

                <!-- PANEL DE NOTIFICACIONES LATERAL -->
                <aside style="width:320px;background:#fff;border-left:1px solid #e2e8f0;display:flex;flex-direction:column;flex-shrink:0;">
                    <div style="padding:20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
                        <h2 style="font-size:14px;font-weight:900;color:#0f172a;margin:0;">NOTIFICACIONES 🔔</h2>
                        <span style="background:#ef4444;color:#fff;font-size:10px;font-weight:900;padding:2px 8px;border-radius:10px;">{{ notificaciones.length }}</span>
                    </div>
                    <div style="flex:1;overflow-y:auto;padding:15px;display:flex;flex-direction:column;gap:10px;">
                        <div v-if="notificaciones.length === 0" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;opacity:0.4;text-align:center;">
                            <span style="font-size:40px;margin-bottom:10px;">📭</span>
                            <p style="font-size:13px;font-weight:700;">Sin avisos recientes</p>
                        </div>
                        <div v-for="n in notificaciones" :key="n.id_detalle" 
                            style="background:#f0fdf4;border-left:4px solid #10b981;padding:12px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.03);animation:slideIn 0.3s ease-out;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                                <span style="font-size:16px;font-weight:900;color:#15803d;">MESA {{ n.mesa }}</span>
                                <span style="font-size:10px;color:#94a3b8;font-weight:700;">{{ n.hora }}</span>
                            </div>
                            <p style="margin:0;font-size:13px;font-weight:700;color:#334155;">¡{{ n.producto }} está LISTO!</p>
                        </div>
                    </div>
                    <div style="padding:15px;background:#f8fafc;border-top:1px solid #f1f5f9;">
                         <button @click="notificaciones = []" style="width:100%;background:transparent;border:1px solid #e2e8f0;color:#64748b;padding:8px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">Limpiar historial</button>
                    </div>
                </aside>
            </div>

            <!-- VISTA: TOMA DE PEDIDO -->
            <div v-if="vista==='pedido'" style="flex:1;display:flex;overflow:hidden;background:#fff;">
                
                <!-- Categorías Lateral -->
                <nav style="width:100px;background:#f8fafc;border-right:1px solid #e2e8f0;display:flex;flex-direction:column;gap:10px;padding:15px 0;align-items:center;flex-shrink:0;">
                    <button @click="catActiva=null" 
                        style="width:70px;height:70px;border-radius:18px;border:none;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;"
                        :style="catActiva===null ? 'background:#0f172a;color:#fff;' : 'background:transparent;color:#64748b;'">
                        <span style="font-size:24px;">📦</span>
                        <span style="font-size:9px;font-weight:800;margin-top:4px;">TODOS</span>
                    </button>
                    <button v-for="cat in categorias" :key="cat.id_categoria" @click="catActiva=cat.id_categoria"
                        style="width:70px;height:70px;border-radius:18px;border:none;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;"
                        :style="catActiva===cat.id_categoria ? 'background:#0f172a;color:#fff;' : 'background:transparent;color:#64748b;'">
                        <span style="font-size:24px;">{{ getCatEmoji(cat.nombre_cat) }}</span>
                        <span style="font-size:9px;font-weight:800;margin-top:4px;text-transform:uppercase;">{{ cat.nombre_cat }}</span>
                    </button>
                </nav>

                <!-- Grid de Productos -->
                <div style="flex:1;padding:25px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill, minmax(140px, 1fr));gap:20px;align-content:start;background:#f8fafc;">
                    <div v-for="prod in menuFiltrado" :key="prod.id_producto" @click="agregar(prod)"
                        style="background:#fff;border-radius:20px;padding:15px;display:flex;flex-direction:column;align-items:center;cursor:pointer;transition:all 0.15s;border:1px solid #e2e8f0;box-shadow:0 2px 4px rgba(0,0,0,0.02);overflow:hidden;"
                        onmouseover="this.style.transform='scale(1.03)';this.style.borderColor='#0f172a'" onmouseout="this.style.transform='scale(1)';this.style.borderColor='#e2e8f0'">
                        <div style="width:100%;aspect-ratio:1.2;background:#f1f5f9;border-radius:12px;margin-bottom:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                            <img v-if="prod.url_imagen" :src="prod.url_imagen" style="width:100%;height:100%;object-fit:cover;">
                            <span v-else style="font-size:40px;">{{ getCatEmoji(prod.categoria?.nombre_cat || '') }}</span>
                        </div>
                        <h4 style="margin:0;font-size:14px;font-weight:800;color:#0f172a;text-align:center;">{{ prod.nombre_prod }}</h4>
                        <p style="margin:8px 0 0 0;font-size:16px;font-weight:900;color:#10b981;">${{ parseFloat(prod.precio).toFixed(2) }}</p>
                    </div>
                </div>

                <!-- Resumen Lateral -->
                <div style="width:380px;background:#fff;border-left:1px solid #e2e8f0;display:flex;flex-direction:column;flex-shrink:0;">
                    <div style="padding:25px;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
                        <h3 style="margin:0;font-size:16px;font-weight:900;color:#0f172a;">DETALLE DEL PEDIDO</h3>
                        <p style="margin:4px 0 0 0;font-size:12px;color:#64748b;font-weight:700;">MESA {{ mesaActual?.id_mesa }}</p>
                    </div>

                    <div style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;">
                        <div v-if="!orden.length" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#cbd5e1;opacity:0.6;">
                            <span style="font-size:60px;margin-bottom:15px;">🛒</span>
                            <p style="font-weight:800;">La canasta está vacía</p>
                        </div>
                        <div v-for="(item, idx) in orden" :key="idx" style="background:#f8fafc;border-radius:15px;padding:15px;">
                            <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                                <span style="font-weight:800;font-size:14px;">{{ item.cant }}x {{ item.producto.nombre_prod }}</span>
                                <span style="font-weight:900;color:#10b981;">${{ (item.cant * item.producto.precio).toFixed(2) }}</span>
                            </div>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <div style="display:flex;background:#fff;border-radius:8px;border:1px solid #e2e8f0;overflow:hidden;">
                                    <button @click="item.cant > 1 ? item.cant-- : orden.splice(idx,1)" style="width:30px;height:30px;border:none;background:#fff;cursor:pointer;">-</button>
                                    <div style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;">{{ item.cant }}</div>
                                    <button @click="item.cant++" style="width:30px;height:30px;border:none;background:#fff;cursor:pointer;">+</button>
                                </div>
                                <input v-model="item.notas" type="text" placeholder="Nota especial..." style="flex:1;background:transparent;border:none;border-bottom:1px solid #e2e8f0;font-size:12px;padding:5px;outline:none;">
                            </div>
                            <div style="display:flex;gap:5px;margin-top:8px;flex-wrap:wrap;">
                                <button v-for="mod in ['Sin cebolla','Extra picante','Para llevar','Bien cocido']" :key="mod" 
                                    @click="item.notas = item.notas ? item.notas + ', ' + mod : mod"
                                    style="background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:3px 8px;font-size:9px;font-weight:800;color:#64748b;cursor:pointer;">+ {{ mod }}</button>
                            </div>
                        </div>
                    </div>

                    <div style="padding:25px;border-top:2px solid #f1f5f9;background:#fff;">
                        <div style="padding:15px;background:#f0fdf4;border-radius:15px;margin-bottom:20px;">
                            <p style="margin:0 0 10px 0;font-size:10px;font-weight:900;color:#15803d;text-transform:uppercase;">Información del Cliente</p>
                            <input v-model="clienteNombre" placeholder="Nombre" style="width:100%;background:#fff;border:1px solid #dcfce7;border-radius:8px;padding:10px;margin-bottom:8px;font-size:13px;outline:none;">
                            <input v-model="clienteNit" placeholder="NIT / C.F." style="width:100%;background:#fff;border:1px solid #dcfce7;border-radius:8px;padding:10px;font-size:13px;outline:none;">
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                            <span style="font-size:14px;font-weight:700;color:#64748b;">TOTAL A PAGAR</span>
                            <span style="font-size:32px;font-weight:900;color:#0f172a;">${{ totalOrden.toFixed(2) }}</span>
                        </div>
                        <button @click="enviarOrden" :disabled="loading || !orden.length"
                            style="width:100%;height:60px;background:#0f172a;color:#fff;border:none;border-radius:18px;font-size:18px;font-weight:900;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:0.2s;"
                            onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='#0f172a'">
                            {{ loading ? '...' : 'ENVIAR A COCINA 🚀' }}
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <!-- MODAL COBRO -->
        <Teleport to="body">
            <div v-if="modalCobro" style="position:fixed;inset:0;background:rgba(15,23,42,0.8);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(5px);">
                <div v-if="!factGenerada" style="background:#fff;width:100%;max-width:500px;border-radius:30px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
                    <div style="padding:30px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <h2 style="margin:0;font-size:22px;font-weight:900;color:#0f172a;">CUENTA MESA {{ mesaActual?.id_mesa }}</h2>
                            <p style="margin:5px 0 0 0;font-size:12px;color:#64748b;font-weight:700;">Ticket #{{ pedidoActivo?.id_pedido }}</p>
                        </div>
                        <button @click="modalCobro=false" style="width:40px;height:40px;border-radius:50%;border:none;background:#fff;cursor:pointer;">✕</button>
                    </div>

                    <div style="padding:30px;">
                        <!-- Status de los platos -->
                        <div style="margin-bottom:25px;display:flex;gap:10px;overflow-x:auto;">
                            <div v-for="d in pedidoActivo?.detalles" :key="d.id_detalle" 
                                style="flex-shrink:0;width:100px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:15px;padding:10px;text-align:center;">
                                <div style="font-size:20px;">
                                     <img v-if="d.producto?.url_imagen" :src="d.producto.url_imagen" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
                                     <span v-else>{{ getCatEmoji(d.producto?.categoria?.nombre_cat || '') }}</span>
                                </div>
                                <p style="margin:5px 0;font-size:10px;font-weight:800;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ d.producto.nombre_prod }}</p>
                                <span :style="d.estado_cocina==='Listo'?'color:#10b981;':'color:#f59e0b;'" style="font-size:9px;font-weight:900;text-transform:uppercase;">{{ d.estado_cocina }}</span>
                            </div>
                        </div>

                        <div style="background:#f0fdf4;border:2px dashed #10b981;border-radius:20px;padding:25px;text-align:center;margin-bottom:25px;">
                            <p style="margin:0;font-size:14px;font-weight:700;color:#15803d;">TOTAL A COBRAR</p>
                            <h3 style="margin:10px 0;font-size:48px;font-weight:900;color:#0f172a;">${{ totalCobro.toFixed(2) }}</h3>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:25px;">
                            <button @click="metodoPago='Efectivo'" 
                                style="height:60px;border-radius:15px;border:2px solid #e2e8f0;background:#fff;font-weight:800;cursor:pointer;transition:0.2s;"
                                :style="metodoPago==='Efectivo'?'border-color:#10b981;background:#f0fdf4;':''">💵 EFECTIVO</button>
                            <button @click="metodoPago='Tarjeta'" 
                                style="height:60px;border-radius:15px;border:2px solid #e2e8f0;background:#fff;font-weight:800;cursor:pointer;transition:0.2s;"
                                :style="metodoPago==='Tarjeta'?'border-color:#10b981;background:#f0fdf4;':''">💳 TARJETA</button>
                        </div>

                        <div v-if="metodoPago==='Efectivo'" style="margin-bottom:25px;">
                            <label style="display:block;margin-bottom:10px;font-size:12px;font-weight:800;color:#64748b;">¿CUÁNTO RECIBIÓ?</label>
                            <input v-model="montoRecibido" type="number" placeholder="0.00" style="width:100%;height:55px;border-radius:15px;border:2px solid #e2e8f0;padding:0 20px;font-size:24px;font-weight:900;outline:none;box-sizing:border-box;">
                            <div v-if="parseFloat(montoRecibido) > 0" style="margin-top:15px;display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-weight:800;color:#64748b;">CAMBIO:</span>
                                <span style="font-size:24px;font-weight:900;color:#10b981;">${{ cambio.toFixed(2) }}</span>
                            </div>
                        </div>

                        <button @click="procesarCobro" :disabled="cobrandoLoad"
                            style="width:100%;height:65px;background:#10b981;color:#fff;border:none;border-radius:20px;font-size:18px;font-weight:900;cursor:pointer;box-shadow:0 10px 15px -3px rgba(16,185,129,0.3);">
                            {{ cobrandoLoad ? 'PROCESANDO...' : 'FINALIZAR Y FACTURAR ✓' }}
                        </button>
                    </div>
                </div>

                <!-- TICKET / FACTURA -->
                <div v-else style="background:#fff;padding:40px;width:100%;max-width:380px;border-radius:4px;box-shadow:0 10px 30px rgba(0,0,0,0.1);font-family:'Courier New', monospace;color:#000;">
                    <div style="text-align:center;border-bottom:1px dashed #000;padding-bottom:15px;margin-bottom:15px;">
                        <h2 style="margin:0;font-size:20px;">HA LA FRIDA</h2>
                        <p style="margin:4px 0;font-size:12px;">Antigua Comida Mexicana</p>
                    </div>
                    
                    <div style="font-size:13px;margin-bottom:15px;">
                        <p style="margin:2px 0;"><strong>NUM:</strong> {{ factGenerada.numero_factura }}</p>
                        <p style="margin:2px 0;"><strong>CLIENTE:</strong> {{ factGenerada.nombre_cliente }}</p>
                        <p style="margin:2px 0;"><strong>NIT:</strong> {{ factGenerada.nit_cliente }}</p>
                    </div>

                    <div style="border-bottom:1px dashed #000;padding-bottom:10px;margin-bottom:10px;">
                        <div v-for="det in pedidoActivo?.detalles" :key="det.id_detalle" style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                            <span>{{ det.cantidad }}x {{ det.producto?.nombre_prod }}</span>
                            <span>${{ (det.cantidad * parseFloat(det.precio_unitario)).toFixed(2) }}</span>
                        </div>
                    </div>

                    <div style="text-align:right;font-size:18px;font-weight:bold;margin-bottom:30px;">
                        TOTAL: ${{ parseFloat(factGenerada.total).toFixed(2) }}
                    </div>

                    <button @click="finalizarTodo" style="width:100%;padding:15px;background:#000;color:#fff;border:none;border-radius:4px;font-weight:bold;cursor:pointer;font-family:sans-serif;">TERMINAR</button>
                </div>
            </div>
        </Teleport>

    </div>
</template>

<style>
@keyframes pulse {
    0% { transform: scale(0.95); opacity: 0.8; }
    50% { transform: scale(1.05); opacity: 1; }
    100% { transform: scale(0.95); opacity: 0.8; }
}
@keyframes slideIn {
    from { transform: translateX(30px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
.slide-enter-active, .slide-leave-active { transition: all 0.4s ease; }
.slide-enter-from, .slide-leave-to { transform: translateX(100px); opacity: 0; }
</style>
