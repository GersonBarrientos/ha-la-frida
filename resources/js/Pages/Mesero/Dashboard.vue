<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ auth: { type: Object } });

// Estado
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
const kitchenLoad = ref({ nivel: 'Bajo', total_items: 0 });
const waiterNotifications = ref([]);

const showNotif = (msg, tipo = 'ok') => { 
    notif.value = { msg, tipo }; 
    setTimeout(() => notif.value = null, 3000); 
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
        waiterNotifications.value = nRes.data;
    } catch (e) { console.error('Error refresh'); }
};

let poll = null;
onMounted(async () => {
    const [mRes, cRes] = await Promise.all([
        axios.get('/api/mesero/menu'),
        axios.get('/api/admin/categorias')
    ]);
    menu.value = mRes.data;
    categorias.value = cRes.data;
    refreshData();
    poll = setInterval(refreshData, 10000);
});
onUnmounted(() => clearInterval(poll));

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

const addToOrder = (p) => {
    const item = orden.value.find(i => i.id_producto === p.id_producto);
    if (item) item.cant++;
    else orden.value.push({ id_producto: p.id_producto, producto: p, cant: 1, notas: '' });
};

const enviarOrden = async () => {
    loading.value = true;
    try {
        await axios.post('/api/mesero/order', {
            id_mesa: mesaActual.value.id_mesa,
            items: orden.value.map(i => ({ id_producto: i.id_producto, cantidad: i.cant, notas: i.notas }))
        });
        showNotif('Orden enviada ✓');
        vista.value = 'mesas';
        refreshData();
    } catch (e) { showNotif('Error al enviar', 'err'); }
    finally { loading.value = false; }
};

const procesarCobro = async () => {
    cobrandoLoad.value = true;
    try {
        await axios.post('/api/mesero/cobrar', { id_pedido: pedidoActivo.value.id_pedido, metodo_pago: metodoPago.value });
        showNotif('Cobrado con éxito ✓');
        modalCobro.value = false;
        vista.value = 'mesas';
        refreshData();
    } catch (e) { showNotif('Error al cobrar', 'err'); }
    finally { cobrandoLoad.value = false; }
};

const totalOrden = computed(() => orden.value.reduce((acc, i) => acc + (i.producto.precio * i.cant), 0));
const totalActivo = computed(() => pedidoActivo.value?.detalles.reduce((acc, i) => acc + (i.precio_unitario * i.cantidad), 0) || 0);

const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Mesero Dashboard" />
    <div class="mesero-layout">
        <!-- Sidebar de Notificaciones -->
        <aside class="notif-sidebar">
            <div class="sidebar-head">
                <h3>🔔 Estados</h3>
                <span class="k-badge" :class="kitchenLoad.nivel">Cocina: {{ kitchenLoad.nivel }}</span>
            </div>
            <div class="notif-scroll">
                <div v-for="n in waiterNotifications" :key="n.id_detalle" class="n-card" :class="n.estado_cocina">
                    <strong>Mesa {{ n.id_mesa }}</strong>
                    <p>{{ n.nombre_prod }}</p>
                    <span class="n-status">{{ n.estado_cocina }}</span>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <main class="main-area">
            <header class="header">
                <div class="h-user">
                    <h2>Hola, {{ auth.user.nombre_completo }}</h2>
                    <p>Mesero en Turno</p>
                </div>
                <button @click="logout" class="btn-logout">Salir</button>
            </header>

            <div v-if="vista === 'mesas'" class="mesas-view">
                <h3>Selecciona Mesa</h3>
                <div class="grid-mesas">
                    <div v-for="m in mesas" :key="m.id_mesa" class="m-box" :class="m.estado" @click="selectMesa(m)">
                        <div class="m-num">{{ m.id_mesa }}</div>
                        <div class="m-st">{{ m.estado }}</div>
                    </div>
                </div>
            </div>

            <div v-else class="pedido-view">
                <div class="pedido-head">
                    <button @click="vista='mesas'" class="btn-back">← Volver</button>
                    <h3>Mesa {{ mesaActual.id_mesa }}</h3>
                </div>

                <div class="pedido-body">
                    <!-- Menu -->
                    <div class="menu-col">
                        <div class="cats">
                            <button @click="catActiva=null" :class="{act: !catActiva}">Todo</button>
                            <button v-for="c in categorias" :key="c.id_categoria" @click="catActiva=c.id_categoria" :class="{act: catActiva===c.id_categoria}">{{ c.nombre_cat }}</button>
                        </div>
                        <div class="menu-grid">
                            <div v-for="p in menuFiltrado" :key="p.id_producto" class="p-item" @click="addToOrder(p)">
                                <h4>{{ p.nombre_prod }}</h4>
                                <span>$ {{ p.precio }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Cuenta -->
                    <div class="cuenta-col">
                        <div v-if="mesaActual.estado === 'Ocupada'" class="cuenta-activa">
                            <h4>Pedido Actual</h4>
                            <div class="items-list">
                                <div v-for="d in pedidoActivo?.detalles" :key="d.id_detalle" class="i-row">
                                    <span>{{ d.cantidad }}x {{ d.producto?.nombre_prod }}</span>
                                    <strong>$ {{ (d.cantidad * d.precio_unitario).toFixed(2) }}</strong>
                                </div>
                            </div>
                            <div class="c-foot">
                                <div class="c-total">Total: $ {{ totalActivo.toFixed(2) }}</div>
                                <button @click="modalCobro=true" class="btn-cobrar">Cerrar y Cobrar</button>
                            </div>
                        </div>
                        <div v-else class="cuenta-nueva">
                            <h4>Nueva Orden</h4>
                            <div class="items-list">
                                <div v-for="item in orden" :key="item.id_producto" class="i-row-new">
                                    <div class="i-top">
                                        <span>{{ item.cant }}x {{ item.producto.nombre_prod }}</span>
                                        <strong>$ {{ (item.cant * item.producto.precio).toFixed(2) }}</strong>
                                    </div>
                                    <input v-model="item.notas" placeholder="Notas..." />
                                </div>
                            </div>
                            <div class="c-foot">
                                <div class="c-total">Total: $ {{ totalOrden.toFixed(2) }}</div>
                                <button @click="enviarOrden" :disabled="!orden.length || loading" class="btn-enviar">Enviar a Cocina</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <div v-if="notif" class="toast" :class="notif.tipo">{{ notif.msg }}</div>

        <!-- Modal Cobro -->
        <div v-if="modalCobro" class="modal">
            <div class="m-content">
                <h3>Cobrar Mesa {{ mesaActual.id_mesa }}</h3>
                <p>Total: <strong>$ {{ totalActivo.toFixed(2) }}</strong></p>
                <select v-model="metodoPago">
                    <option value="Efectivo">Efectivo</option>
                    <option value="Tarjeta">Tarjeta</option>
                </select>
                <div class="m-btns">
                    <button @click="modalCobro=false">Cancelar</button>
                    <button @click="procesarCobro" class="btn-enviar" :disabled="cobrandoLoad">Confirmar</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mesero-layout { display: flex; height: 100vh; background: #0f172a; color: #fff; font-family: sans-serif; }
.notif-sidebar { width: 280px; background: #1e293b; border-right: 1px solid #334155; display: flex; flex-direction: column; }
.sidebar-head { padding: 20px; border-bottom: 1px solid #334155; }
.k-badge { font-size: 11px; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
.k-badge.Bajo { background: #065f46; color: #34d399; }
.k-badge.Alto { background: #991b1b; color: #f87171; }

.notif-scroll { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 10px; }
.n-card { background: #334155; padding: 12px; border-radius: 8px; border-left: 4px solid #64748b; }
.n-card.Listo { border-color: #10b981; background: #064e3b; }
.n-card.En.Preparación { border-color: #f59e0b; }
.n-status { font-size: 10px; font-weight: bold; text-transform: uppercase; opacity: 0.8; }

.main-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.header { padding: 20px 30px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; }
.btn-logout { background: transparent; border: 1px solid #ef4444; color: #ef4444; padding: 8px 15px; border-radius: 6px; cursor: pointer; }

.mesas-view { padding: 30px; overflow-y: auto; }
.grid-mesas { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 20px; }
.m-box { background: #fff; color: #0f172a; padding: 25px; border-radius: 12px; text-align: center; cursor: pointer; }
.m-box.Ocupada { background: #1e293b; color: #fff; border: 2px solid #10b981; }
.m-num { font-size: 24px; font-weight: bold; }
.m-st { font-size: 12px; opacity: 0.7; }

.pedido-view { display: flex; flex-direction: column; height: 100%; }
.pedido-head { padding: 15px 30px; display: flex; gap: 20px; align-items: center; background: #334155; }
.pedido-body { display: flex; flex: 1; overflow: hidden; }

.menu-col { flex: 1; padding: 20px; overflow-y: auto; border-right: 1px solid #334155; }
.cats { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 10px; }
.cats button { background: #334155; border: none; color: #fff; padding: 8px 15px; border-radius: 6px; cursor: pointer; white-space: nowrap; }
.cats button.act { background: #10b981; }
.menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.p-item { background: #1e293b; padding: 15px; border-radius: 8px; cursor: pointer; text-align: center; }
.p-item:hover { background: #334155; }

.cuenta-col { width: 350px; background: #1e293b; padding: 20px; display: flex; flex-direction: column; }
.items-list { flex: 1; overflow-y: auto; margin: 15px 0; }
.i-row, .i-row-new { padding: 10px 0; border-bottom: 1px solid #334155; }
.i-row-new input { width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 5px; margin-top: 5px; border-radius: 4px; }
.c-foot { padding-top: 15px; border-top: 2px dashed #334155; }
.c-total { font-size: 20px; font-weight: bold; margin-bottom: 15px; }
.btn-enviar, .btn-cobrar { width: 100%; background: #10b981; color: #fff; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; }

.toast { position: fixed; bottom: 20px; right: 20px; padding: 12px 25px; border-radius: 8px; color: #fff; font-weight: bold; }
.toast.ok { background: #10b981; }
.toast.err { background: #ef4444; }

.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; }
.m-content { background: #1e293b; padding: 30px; border-radius: 12px; width: 300px; text-align: center; }
.m-content select { width: 100%; padding: 10px; margin: 15px 0; background: #0f172a; color: #fff; border: 1px solid #334155; }
.m-btns { display: flex; gap: 10px; margin-top: 20px; }
.m-btns button { flex: 1; padding: 10px; cursor: pointer; }
</style>
