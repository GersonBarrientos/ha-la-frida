<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ auth: { type: Object } });

const orders = ref([]);
const loading = ref(false);
const notif = ref(null);

const showNotif = (msg, tipo = 'ok') => { 
    notif.value = { msg, tipo }; 
    setTimeout(() => notif.value = null, 4000); 
};

const refreshOrders = async () => {
    try {
        const res = await axios.get('/api/cocina/orders');
        orders.value = res.data;
    } catch (e) { console.error('Error fetching kitchen orders'); }
};

let interval = null;
onMounted(() => {
    refreshOrders();
    interval = setInterval(refreshOrders, 5000); // Poll every 5s
});
onUnmounted(() => clearInterval(interval));

const changeStatus = async (id_detalle, newStatus) => {
    try {
        await axios.post(`/api/cocina/orders/${id_detalle}/status`, { estado_cocina: newStatus });
        showNotif(`Plato marcado como ${newStatus} ✓`);
        refreshOrders();
    } catch (e) { showNotif('Error al actualizar estado', 'err'); }
};

const cancelar = async (id_detalle) => {
    if (!confirm('¿Seguro que quieres cancelar este ítem? Esto devolverá los insumos al inventario.')) return;
    try {
        await axios.post(`/api/cocina/orders/${id_detalle}/cancelar`);
        showNotif('Ítem cancelado ❌', 'err');
        refreshOrders();
    } catch (e) { showNotif('Error al cancelar', 'err'); }
};

const logout = () => router.post(route('logout'));
</script>

<template>
    <Head title="Cocina - Ha La Frida" />
    <div class="kitchen-container">
        <header class="kitchen-header">
            <div class="brand">🍳 COCINA <span>Ha La Frida</span></div>
            <div class="stats">
                <strong>{{ orders.length }}</strong> Comandas Activas
            </div>
            <button @click="logout" class="btn-logout">Cerrar Sesión</button>
        </header>

        <main class="orders-grid">
            <div v-for="order in orders" :key="order.id_pedido" class="order-ticket fade-in">
                <div class="ticket-header">
                    <div class="mesa">Mesa {{ order.mesa?.id_mesa }}</div>
                    <div class="time">{{ new Date(order.fecha_hora).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</div>
                </div>
                
                <div class="ticket-body">
                    <div v-for="d in order.detalles" :key="d.id_detalle" class="item-row" :class="d.estado_cocina">
                        <div class="item-info">
                            <span class="qty">{{ d.cantidad }}x</span>
                            <span class="name">{{ d.producto?.nombre_prod }}</span>
                            <p v-if="d.notas" class="notes">📝 {{ d.notas }}</p>
                        </div>
                        
                        <div class="item-actions">
                            <button v-if="d.estado_cocina === 'Recibido'" @click="changeStatus(d.id_detalle, 'En Preparación')" class="btn-prep">👨‍🍳 Preparar</button>
                            <button v-if="d.estado_cocina === 'En Preparación'" @click="changeStatus(d.id_detalle, 'Listo')" class="btn-ready">✅ Listo</button>
                            <span v-if="d.estado_cocina === 'Listo'" class="badge-ready">ESPERANDO MESERO</span>
                            <button @click="cancelar(d.id_detalle)" class="btn-cancel">❌</button>
                        </div>
                    </div>
                </div>
                <div class="ticket-footer">
                    Pedido #{{ order.id_pedido }} · Mesero: {{ order.usuario?.nombre_completo || 'N/A' }}
                </div>
            </div>
        </main>

        <div v-if="notif" class="toast" :class="notif.tipo">{{ notif.msg }}</div>
    </div>
</template>

<style scoped>
.kitchen-container { min-height: 100vh; background: #0f172a; color: #fff; display: flex; flex-direction: column; }

.kitchen-header { background: #1e293b; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #334155; }
.brand { font-size: 20px; font-weight: 800; color: #10b981; }
.brand span { color: #fff; font-weight: 400; opacity: 0.7; }
.stats { background: #334155; padding: 8px 15px; border-radius: 12px; font-size: 14px; }
.btn-logout { background: #ef444422; color: #ef4444; border: 1px solid #ef444444; padding: 8px 15px; border-radius: 8px; cursor: pointer; }

.orders-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; padding: 30px; }
.order-ticket { background: #fff; color: #1e293b; border-radius: 20px; display: flex; flex-direction: column; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); border-top: 8px solid #10b981; }

.ticket-header { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.mesa { font-size: 20px; font-weight: 800; }
.time { font-weight: 700; color: #64748b; }

.ticket-body { flex: 1; padding: 20px; display: flex; flex-direction: column; gap: 15px; }
.item-row { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 15px; border-bottom: 1px solid #f1f5f9; }
.item-row.En.Preparación { background: #fffbeb; margin: -10px; padding: 10px; border-radius: 10px; }
.item-row.Listo { opacity: 0.5; background: #f0fdf4; margin: -10px; padding: 10px; border-radius: 10px; }

.qty { font-weight: 800; color: #10b981; margin-right: 10px; }
.name { font-weight: 700; }
.notes { margin: 5px 0 0; font-size: 11px; color: #ef4444; font-weight: 600; }

.item-actions { display: flex; gap: 8px; align-items: center; }
.btn-prep { background: #f59e0b; color: #fff; border: none; padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; }
.btn-ready { background: #10b981; color: #fff; border: none; padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; }
.btn-cancel { background: #f1f5f9; border: none; padding: 8px; border-radius: 8px; cursor: pointer; }
.badge-ready { background: #dcfce7; color: #16a34a; font-size: 10px; font-weight: 800; padding: 4px 8px; border-radius: 50px; }

.ticket-footer { padding: 10px 20px; background: #f8fafc; font-size: 10px; color: #94a3b8; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; text-transform: uppercase; }

.toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); padding: 15px 30px; border-radius: 10px; color: #fff; font-weight: 700; }
.toast.ok { background: #10b981; }
.toast.err { background: #ef4444; }

.fade-in { animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

@media (max-width: 600px) {
    .orders-grid { grid-template-columns: 1fr; padding: 15px; }
}
</style>
