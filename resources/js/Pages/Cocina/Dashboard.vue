<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ auth: { type: Object } });
const orders = ref([]);
const notif = ref(null);

const showNotif = (msg, tipo = 'ok') => { 
    notif.value = { msg, tipo }; 
    setTimeout(() => notif.value = null, 3000); 
};

const refresh = async () => {
    try {
        const res = await axios.get('/api/cocina/orders');
        orders.value = res.data;
    } catch (e) { console.error('Error refresh'); }
};

let poll = null;
onMounted(() => {
    refresh();
    poll = setInterval(refresh, 5000);
});
onUnmounted(() => clearInterval(poll));

const updateStatus = async (id, status) => {
    try {
        await axios.post(`/api/cocina/orders/${id}/status`, { estado_cocina: status });
        showNotif(`Actualizado a ${status} ✓`);
        refresh();
    } catch (e) { showNotif('Error', 'err'); }
};

const cancelar = async (id) => {
    if (!confirm('¿Cancelar ítem?')) return;
    try {
        await axios.post(`/api/cocina/orders/${id}/cancelar`);
        showNotif('Cancelado ❌', 'err');
        refresh();
    } catch (e) { showNotif('Error', 'err'); }
};

const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Cocina" />
    <div class="cocina-layout">
        <header>
            <div class="brand">🍳 COCINA <span>Ha La Frida</span></div>
            <div class="stats"><strong>{{ orders.length }}</strong> Ordenes Activas</div>
            <button @click="logout" class="btn-logout">Salir</button>
        </header>

        <main class="grid">
            <div v-for="o in orders" :key="o.id_pedido" class="ticket">
                <div class="t-head">
                    <span>Mesa {{ o.mesa?.id_mesa }}</span>
                    <span>{{ new Date(o.fecha_hora).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) }}</span>
                </div>
                <div class="t-body">
                    <div v-for="d in o.detalles" :key="d.id_detalle" class="item" :class="d.estado_cocina">
                        <div class="info">
                            <strong>{{ d.cantidad }}x {{ d.producto?.nombre_prod }}</strong>
                            <p v-if="d.notas" class="notes">{{ d.notas }}</p>
                        </div>
                        <div class="actions">
                            <button v-if="d.estado_cocina==='Recibido'" @click="updateStatus(d.id_detalle, 'En Preparación')" class="btn-prep">Preparar</button>
                            <button v-if="d.estado_cocina==='En Preparación'" @click="updateStatus(d.id_detalle, 'Listo')" class="btn-ready">Listo</button>
                            <span v-if="d.estado_cocina==='Listo'" class="badge">LISTO</span>
                            <button @click="cancelar(d.id_detalle)" class="btn-del">❌</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <div v-if="notif" class="toast" :class="notif.tipo">{{ notif.msg }}</div>
    </div>
</template>

<style scoped>
.cocina-layout { min-height: 100vh; background: #0f172a; color: #fff; font-family: sans-serif; }
header { background: #1e293b; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #334155; }
.brand { font-weight: bold; font-size: 18px; color: #10b981; }
.btn-logout { background: transparent; border: 1px solid #ef4444; color: #ef4444; padding: 8px 15px; border-radius: 6px; cursor: pointer; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; padding: 30px; }
.ticket { background: #fff; color: #1e293b; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; border-top: 6px solid #10b981; }
.t-head { padding: 15px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; font-weight: bold; }
.t-body { padding: 15px; flex: 1; display: flex; flex-direction: column; gap: 10px; }

.item { display: flex; justify-content: space-between; align-items: flex-start; padding: 10px; border-radius: 6px; background: #f8fafc; }
.item.En.Preparación { background: #fffbeb; }
.item.Listo { background: #f0fdf4; opacity: 0.7; }

.notes { color: #ef4444; font-size: 11px; margin: 4px 0 0; }
.actions { display: flex; gap: 5px; align-items: center; }

.btn-prep { background: #f59e0b; color: #fff; border: none; padding: 6px 10px; border-radius: 4px; font-weight: bold; cursor: pointer; }
.btn-ready { background: #10b981; color: #fff; border: none; padding: 6px 10px; border-radius: 4px; font-weight: bold; cursor: pointer; }
.btn-del { background: transparent; border: none; cursor: pointer; }
.badge { background: #dcfce7; color: #16a34a; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 10px; }

.toast { position: fixed; bottom: 20px; right: 20px; padding: 12px 25px; border-radius: 8px; color: #fff; font-weight: bold; }
.toast.ok { background: #10b981; }
.toast.err { background: #ef4444; }
</style>
