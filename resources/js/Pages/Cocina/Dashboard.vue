<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ auth: { type: Object } });

const ordenes = ref([]);
const ultimaActualizacion = ref('');
let pollingInterval = null;

// Audio para notificaciones
const playBell = () => {
    try {
        const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
        audio.play();
    } catch (e) {}
};

const fetchOrders = async () => {
    try {
        const res = await axios.get('/api/cocina/orders');
        const nuevasOrdenes = res.data;
        
        if (nuevasOrdenes.length > ordenes.value.length) {
            const nuevas = nuevasOrdenes.filter(no => !ordenes.value.find(o => o.id_pedido === no.id_pedido));
            if (nuevas.length > 0) playBell();
        }
        
        ordenes.value = nuevasOrdenes;
        ultimaActualizacion.value = new Date().toLocaleTimeString('es-GT');
    } catch (e) {
        console.error("Error fetching kitchen orders:", e);
    }
};

const getDetallesPorEstado = (estado) => {
    let result = [];
    ordenes.value.forEach(pedido => {
        pedido.detalles?.forEach(det => {
            if (det.estado_cocina === estado) {
                result.push({ ...det, pedido_info: pedido });
            }
        });
    });
    return result;
};

// Resumen de totales para el cocinero
const totalesCocina = computed(() => {
    const todos = getDetallesPorEstado('Recibido').concat(getDetallesPorEstado('En Preparación'));
    const res = {};
    todos.forEach(d => {
        const nom = d.producto?.nombre_prod;
        res[nom] = (res[nom] || 0) + d.cantidad;
    });
    return res;
});

const updateStatus = async (idDetalle, newStatus) => {
    try {
        await axios.post(`/api/cocina/orders/${idDetalle}/status`, { estado_cocina: newStatus });
        await fetchOrders();
    } catch (e) {
        alert("Error al actualizar estado");
    }
};

const cancelarItem = async (det) => {
    if (!confirm(`¿Reportar indisponibilidad para ${det.producto?.nombre_prod}?`)) return;
    try {
        await axios.post(`/api/cocina/orders/${det.id_detalle}/cancelar`);
        await fetchOrders();
    } catch (e) {
        alert("Error al cancelar ítem");
    }
};

const getTiempoColor = (fecha) => {
    const diff = Math.floor((new Date() - new Date(fecha)) / 60000);
    if (diff > 12) return '#ef4444'; // Rojo (Tarde)
    if (diff > 7) return '#f59e0b';  // Naranja (Advertencia)
    return '#10b981'; // Verde (Bien)
};

const tiempoTranscurrido = (fecha) => {
    const diff = Math.floor((new Date() - new Date(fecha)) / 60000);
    return diff < 1 ? '¡Recién!' : `${diff}m`;
};

onMounted(() => {
    fetchOrders();
    pollingInterval = setInterval(fetchOrders, 4000);
});

onUnmounted(() => clearInterval(pollingInterval));
const cerrarSesion = () => router.post(route('logout'));
</script>

<template>
    <Head title="Monitor KDS — Ha La Frida" />

    <div style="height:100vh;background:#f3f4f6;color:#111827;font-family:'Segoe UI',system-ui,sans-serif;display:flex;flex-direction:column;overflow:hidden;">

        <!-- Header Cocina -->
        <header style="background:#1f2937;color:#fff;padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:65px;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:15px;">
                <span style="font-size:24px;">🌮</span>
                <h1 style="font-size:18px;font-weight:900;margin:0;letter-spacing:-0.5px;">MONITOR COCINA (KDS)</h1>
                <div style="background:#374151;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;color:#9ca3af;">{{ ultimaActualizacion }}</div>
            </div>

            <!-- Resumen de Totales Flotante (Horizontal) -->
            <div style="display:flex;gap:12px;overflow-x:auto;max-width:50%;padding:0 10px;">
                <div v-for="(cant, prod) in totalesCocina" :key="prod" style="background:#374151;border-radius:8px;padding:6px 12px;display:flex;align-items:center;gap:8px;white-space:nowrap;">
                    <span style="font-weight:900;color:#10b981;">{{ cant }}x</span>
                    <span style="font-size:12px;font-weight:600;color:#fff;">{{ prod }}</span>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:15px;">
                <span style="font-size:13px;font-weight:600;">{{ auth.user.nombre_completo }}</span>
                <button @click="cerrarSesion" style="background:#ef4444;border:none;color:#fff;border-radius:8px;padding:8px 16px;cursor:pointer;font-size:12px;font-weight:700;">SALIR</button>
            </div>
        </header>

        <!-- Tablero Kanban -->
        <div style="flex:1;display:grid;grid-template-columns:1fr 1fr 1fr;gap:2px;background:#e5e7eb;overflow:hidden;">

            <!-- COLUMNA: NUEVAS -->
            <div style="display:flex;flex-direction:column;background:#fff;">
                <div style="padding:15px 20px;border-bottom:2px solid #ef4444;background:#fff;display:flex;justify-content:space-between;align-items:center;">
                    <h2 style="font-size:14px;font-weight:900;color:#ef4444;margin:0;">NUEVAS ÓRDENES</h2>
                    <span style="background:#fee2e2;color:#ef4444;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:900;">{{ getDetallesPorEstado('Recibido').length }}</span>
                </div>
                <div style="flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:12px;">
                    <div v-for="det in getDetallesPorEstado('Recibido')" :key="det.id_detalle" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.06);overflow:hidden;">
                        <div :style="`height:6px;background:${getTiempoColor(det.pedido_info?.fecha_hora)}`" style="width:100%;"></div>
                        <div style="padding:15px;">
                            <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                                <span style="font-size:22px;font-weight:900;color:#111827;">MESA {{ det.pedido_info?.mesa?.id_mesa }}</span>
                                <span style="font-size:12px;font-weight:800;color:#6b7280;">{{ tiempoTranscurrido(det.pedido_info?.fecha_hora) }}</span>
                            </div>
                            <div style="display:flex;align-items:start;gap:12px;margin-bottom:12px;">
                                <div v-if="det.producto?.url_imagen" style="width:50px;height:50px;border-radius:8px;overflow:hidden;flex-shrink:0;">
                                    <img :src="det.producto.url_imagen" style="width:100%;height:100%;object-fit:cover;">
                                </div>
                                <span v-else style="background:#111827;color:#fff;min-width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:900;">{{ det.cantidad }}</span>
                                <div>
                                    <p v-if="det.producto?.url_imagen" style="margin:0;font-size:14px;font-weight:900;color:#111827;">CANT: {{ det.cantidad }}</p>
                                    <p style="margin:0;font-size:18px;font-weight:800;color:#111827;">{{ det.producto?.nombre_prod }}</p>
                                </div>
                            </div>
                            <div v-if="det.notas" style="background:#fffbeb;border-left:4px solid #f59e0b;padding:8px 12px;font-size:13px;font-weight:700;color:#92400e;margin-bottom:12px;border-radius:4px;">
                                {{ det.notas }}
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button @click="updateStatus(det.id_detalle, 'En Preparación')" style="flex:1;background:#10b981;color:#fff;border:none;padding:10px;border-radius:8px;font-weight:800;font-size:13px;cursor:pointer;box-shadow:0 2px 4px rgba(16,185,129,0.2);">COCINAR 👨‍🍳</button>
                                <button @click="cancelarItem(det)" style="background:#f3f4f6;border:none;color:#9ca3af;width:40px;border-radius:8px;cursor:pointer;font-size:18px;">🚫</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMNA: EN PREPARACIÓN -->
            <div style="display:flex;flex-direction:column;background:#fff;">
                <div style="padding:15px 20px;border-bottom:2px solid #f59e0b;background:#fff;display:flex;justify-content:space-between;align-items:center;">
                    <h2 style="font-size:14px;font-weight:900;color:#f59e0b;margin:0;">EN PREPARACIÓN</h2>
                    <span style="background:#fef3c7;color:#f59e0b;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:900;">{{ getDetallesPorEstado('En Preparación').length }}</span>
                </div>
                <div style="flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:12px;">
                    <div v-for="det in getDetallesPorEstado('En Preparación')" :key="det.id_detalle" style="background:#fff;border:1.5px solid #f59e0b;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.06);overflow:hidden;">
                        <div style="padding:15px;">
                            <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                                <span style="font-size:22px;font-weight:900;color:#f59e0b;">MESA {{ det.pedido_info?.mesa?.id_mesa }}</span>
                                <span style="font-size:12px;font-weight:800;color:#6b7280;">{{ tiempoTranscurrido(det.pedido_info?.fecha_hora) }}</span>
                            </div>
                            <div style="display:flex;align-items:start;gap:12px;margin-bottom:12px;">
                                <div v-if="det.producto?.url_imagen" style="width:50px;height:50px;border-radius:8px;overflow:hidden;flex-shrink:0;">
                                    <img :src="det.producto.url_imagen" style="width:100%;height:100%;object-fit:cover;">
                                </div>
                                <span v-else style="background:#f59e0b;color:#fff;min-width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:900;">{{ det.cantidad }}</span>
                                <div>
                                    <p v-if="det.producto?.url_imagen" style="margin:0;font-size:14px;font-weight:900;color:#f59e0b;">CANT: {{ det.cantidad }}</p>
                                    <p style="margin:0;font-size:18px;font-weight:800;color:#111827;">{{ det.producto?.nombre_prod }}</p>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button @click="updateStatus(det.id_detalle, 'Recibido')" style="background:#f3f4f6;border:none;color:#6b7280;padding:10px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;">← VOLVER</button>
                                <button @click="updateStatus(det.id_detalle, 'Listo')" style="flex:1;background:#f59e0b;color:#fff;border:none;padding:10px;border-radius:8px;font-weight:800;font-size:13px;cursor:pointer;box-shadow:0 2px 4px rgba(245,158,11,0.2);">TERMINAR ✓</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMNA: LISTOS -->
            <div style="display:flex;flex-direction:column;background:#f9fafb;">
                <div style="padding:15px 20px;border-bottom:2px solid #10b981;background:#fff;display:flex;justify-content:space-between;align-items:center;">
                    <h2 style="font-size:14px;font-weight:900;color:#10b981;margin:0;">PARA ENTREGA</h2>
                    <span style="background:#d1fae5;color:#10b981;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:900;">{{ getDetallesPorEstado('Listo').length }}</span>
                </div>
                <div style="flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:10px;">
                    <div v-for="det in getDetallesPorEstado('Listo')" :key="det.id_detalle" style="background:#fff;border:1px solid #d1fae5;border-radius:10px;padding:12px;display:flex;justify-content:space-between;align-items:center;opacity:0.85;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="font-size:18px;font-weight:900;color:#10b981;">M{{ det.pedido_info?.mesa?.id_mesa }}</span>
                            <div>
                                <p style="margin:0;font-size:14px;font-weight:700;color:#374151;">{{ det.cantidad }}x {{ det.producto?.nombre_prod }}</p>
                                <p style="margin:0;font-size:11px;color:#9ca3af;">Ticket #{{ det.id_pedido }}</p>
                            </div>
                        </div>
                        <button @click="updateStatus(det.id_detalle, 'Entregado')" style="background:#10b981;border:none;color:#fff;border-radius:6px;padding:6px 12px;font-size:11px;font-weight:800;cursor:pointer;">ENTREGAR</button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style>
@keyframes pulse {
    0% { transform: scale(0.95); opacity: 0.5; }
    50% { transform: scale(1); opacity: 1; }
    100% { transform: scale(0.95); opacity: 0.5; }
}
</style>
