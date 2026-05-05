<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ auth: { type: Object } });
const tab = ref('dashboard');
const notif = ref(null);
const showNotif = (msg, tipo = 'ok') => { notif.value = { msg, tipo }; setTimeout(() => notif.value = null, 3500); };

const stats = ref({ ventas_hoy: { Cantidad_Facturas: 0, Ingresos_Totales: 0 }, inventario_critico: [], total_insumos: 0, total_productos: 0 });

const insumos = ref([]);
const fInsumo = ref({ nombre_insumo: '', unidad_medida: 'gramos', stock_actual: 0, estado: 'Activo' });
const modalInsumo = ref(false);
const editInsumoId = ref(null);

const usuarios = ref([]);
const fUsuario = ref({ nombre_completo: '', correo: '', pin_acceso: '', id_rol: '' });
const fCategoria = ref({ nombre_cat: '' });
const modalCategoria = ref(false);
const modalUsuario = ref(false);



const history = ref([]);
const profitData = ref({ ingresos: 0, costos: 0, utilidad: 0 });

const productos = ref([]);
const categorias = ref([]);
const fProducto = ref({ nombre_prod: '', precio: '', descripcion: '', id_categoria: '', estado: 'Activo' });
const productoImagen = ref(null);
const modalProducto = ref(false);
const editProductoId = ref(null);
const filtroCat = ref(null);

const menuFiltrado = computed(() => {
    if (!filtroCat.value) return productos.value;
    return productos.value.filter(p => p.id_categoria == filtroCat.value);
});

const productoReceta = ref(null);
const recetaActual = ref([]);
const fReceta = ref({ id_insumo: '', cantidad_necesaria: '' });
const editRecetaId = ref(null);

const criticos = computed(() => insumos.value.filter(i => parseFloat(i.stock_actual) <= 10));

const refreshAll = async () => {
    try {
        const [s, ins, prod, cats, usr, prof, hist] = await Promise.all([
            axios.get('/api/admin/stats'),
            axios.get('/api/admin/insumos'),
            axios.get('/api/admin/productos'),
            axios.get('/api/admin/categorias'),
            axios.get('/api/admin/usuarios'),
            axios.get('/api/admin/profit'),
            axios.get('/api/admin/history'),
        ]);
        stats.value = s.data;
        insumos.value = ins.data;
        productos.value = prod.data;
        categorias.value = cats.data;
        usuarios.value = usr.data;
        profitData.value = prof.data;
        history.value = hist.data;
    } catch(e) { console.error('Error loading data:', e); }
};

const guardarCategoria = async () => {
    try {
        await axios.post('/api/admin/categorias', fCategoria.value);
        showNotif('Categoría creada ✓');
        modalCategoria.value = false;
        fCategoria.value = { nombre_cat: '' };
        await refreshAll();
    } catch (e) { showNotif(e.response?.data?.message || 'Error', 'err'); }
};

const eliminarCategoria = async (id) => {
    if (!confirm('¿Eliminar esta categoría?')) return;
    try {
        await axios.delete(`/api/admin/categorias/${id}`);
        showNotif('Categoría eliminada');
        await refreshAll();
    } catch (e) { showNotif(e.response?.data?.error || 'Error', 'err'); }
};

onMounted(refreshAll);

const guardarUsuario = async () => {
    try {
        await axios.post('/api/admin/usuarios', fUsuario.value);
        showNotif('Usuario creado ✓');
        modalUsuario.value = false;
        await refreshAll();
    } catch (e) { showNotif(e.response?.data?.message || 'Error', 'err'); }
};



const guardarInsumo = async () => {
    try {
        if (editInsumoId.value) {
            await axios.put(`/api/admin/insumos/${editInsumoId.value}`, fInsumo.value);
            showNotif('Insumo actualizado ✓');
        } else {
            await axios.post('/api/admin/insumos', fInsumo.value);
            showNotif('Insumo registrado ✓');
        }
        modalInsumo.value = false;
        await refreshAll();
    } catch (e) { showNotif(e.response?.data?.message || 'Error', 'err'); }
};

const prepararEditInsumo = (ins) => {
    editInsumoId.value = ins.id_insumo;
    fInsumo.value = { ...ins };
    modalInsumo.value = true;
};

const eliminarInsumo = async (id) => {
    if (!confirm('¿Seguro que deseas eliminar este insumo?')) return;
    try {
        await axios.delete(`/api/admin/insumos/${id}`);
        showNotif('Insumo eliminado');
        await refreshAll();
    } catch (e) { showNotif(e.response?.data?.error || 'Error', 'err'); }
};

const onFileChange = (e) => {
    productoImagen.value = e.target.files[0];
};

const guardarProducto = async () => {
    const formData = new FormData();
    formData.append('nombre_prod', fProducto.value.nombre_prod);
    formData.append('precio', fProducto.value.precio);
    formData.append('id_categoria', fProducto.value.id_categoria);
    formData.append('descripcion', fProducto.value.descripcion || '');
    formData.append('estado', fProducto.value.estado);
    if (productoImagen.value) {
        formData.append('imagen', productoImagen.value);
    }
    
    if (editProductoId.value) {
        formData.append('_method', 'PUT');
    }

    try {
        const url = editProductoId.value ? `/api/admin/productos/${editProductoId.value}` : '/api/admin/productos';
        await axios.post(url, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        showNotif('Producto guardado ✓');
        modalProducto.value = false;
        productoImagen.value = null;
        await refreshAll();
    } catch (e) { showNotif('Error al guardar producto', 'err'); }
};

const prepararEditProducto = (prod) => {
    editProductoId.value = prod.id_producto;
    fProducto.value = { ...prod };
    modalProducto.value = true;
};

const eliminarProducto = async (id) => {
    if (!confirm('¿Deseas dar de baja este platillo? (Pasará a Inactivo)')) return;
    await axios.delete(`/api/admin/productos/${id}`);
    showNotif('Platillo desactivado');
    await refreshAll();
};

const abrirReceta = async (prod) => {
    productoReceta.value = prod;
    recetaActual.value = (await axios.get(`/api/admin/receta/${prod.id_producto}`)).data;
    tab.value = 'receta';
};

const agregarIngrediente = async () => {
    try {
        if (editRecetaId.value) {
            await axios.put(`/api/admin/recetas/${editRecetaId.value}`, { cantidad_necesaria: fReceta.value.cantidad_necesaria });
            showNotif('Cantidad actualizada ✓');
        } else {
            await axios.post('/api/admin/recetas', { id_producto: productoReceta.value.id_producto, ...fReceta.value });
            showNotif('Ingrediente agregado ✓');
        }
        fReceta.value = { id_insumo: '', cantidad_necesaria: '' };
        editRecetaId.value = null;
        recetaActual.value = (await axios.get(`/api/admin/receta/${productoReceta.value.id_producto}`)).data;
    } catch (e) { showNotif('Error en receta', 'err'); }
};

const prepararEditReceta = (r) => {
    editRecetaId.value = r.id_receta;
    fReceta.value = { id_insumo: r.id_insumo, cantidad_necesaria: r.cantidad_necesaria };
};

const eliminarIngrediente = async (id) => {
    await axios.delete(`/api/admin/receta/${id}`);
    recetaActual.value = (await axios.get(`/api/admin/receta/${productoReceta.value.id_producto}`)).data;
    showNotif('Ingrediente eliminado');
};
</script>

<template>
    <Head title="Administración — Ha La Frida" />

    <!-- MODAL INSUMO -->
    <Teleport to="body">
        <div v-if="modalInsumo" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:14px;padding:24px;width:100%;max-width:400px;box-shadow:0 20px 40px rgba(0,0,0,0.2);">
                <h2 style="font-size:18px;font-weight:800;margin:0 0 20px 0;">{{ editInsumoId ? 'Editar' : 'Nuevo' }} Insumo</h2>
                <form @submit.prevent="guardarInsumo">
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Nombre</label>
                        <input type="text" v-model="fInsumo.nombre_insumo" required style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;box-sizing:border-box;" />
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Unidad</label>
                            <select v-model="fInsumo.unidad_medida" style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;background:#fff;box-sizing:border-box;">
                                <option v-for="o in ['gramos','kilogramos','unidades','litros','porciones']" :key="o" :value="o">{{ o }}</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Stock</label>
                            <input type="number" step="0.01" v-model="fInsumo.stock_actual" required style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;box-sizing:border-box;" />
                        </div>
                    </div>

                    <div v-if="editInsumoId" style="margin-bottom:20px;">
                        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Estado</label>
                        <select v-model="fInsumo.estado" style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;background:#fff;box-sizing:border-box;">
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <button type="button" @click="modalInsumo=false" style="padding:12px;background:#f3f4f6;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Cerrar</button>
                        <button type="submit" style="padding:12px;background:#16a34a;border:none;border-radius:8px;color:#fff;font-weight:700;cursor:pointer;">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- MODAL PRODUCTO -->
    <Teleport to="body">
        <div v-if="modalProducto" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:14px;padding:24px;width:100%;max-width:440px;box-shadow:0 20px 40px rgba(0,0,0,0.2);">
                <h2 style="font-size:18px;font-weight:800;margin:0 0 20px 0;">{{ editProductoId ? 'Editar' : 'Nuevo' }} Platillo</h2>
                <form @submit.prevent="guardarProducto">
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Nombre del Platillo</label>
                        <input type="text" v-model="fProducto.nombre_prod" required style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;box-sizing:border-box;" />
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Precio ($)</label>
                            <input type="number" step="0.01" v-model="fProducto.precio" required style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;box-sizing:border-box;" />
                        </div>
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Categoría</label>
                            <select v-model="fProducto.id_categoria" required style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;background:#fff;box-sizing:border-box;">
                                <option v-for="c in categorias" :key="c.id_categoria" :value="c.id_categoria">{{ c.nombre_cat }}</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Descripción</label>
                        <textarea v-model="fProducto.descripcion" style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;box-sizing:border-box;height:60px;resize:none;"></textarea>
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Imagen del Platillo</label>
                        <input type="file" @change="onFileChange" accept="image/*" style="width:100%;font-size:13px;" />
                        <p style="font-size:10px;color:#9ca3af;margin-top:4px;">Dejar vacío para mantener la actual o usar emoji por defecto.</p>
                    </div>
                    <div v-if="editProductoId" style="margin-bottom:20px;">
                        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Estado</label>
                        <select v-model="fProducto.estado" style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;background:#fff;box-sizing:border-box;">
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <button type="button" @click="modalProducto=false" style="padding:12px;background:#f3f4f6;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Cerrar</button>
                        <button type="submit" style="padding:12px;background:#2563eb;border:none;border-radius:8px;color:#fff;font-weight:700;cursor:pointer;">Guardar Platillo</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Toast -->
    <Transition name="slide">
        <div v-if="notif" style="position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:600;font-family:'Segoe UI',sans-serif;box-shadow:0 8px 24px rgba(0,0,0,0.12);"
            :style="notif.tipo==='err' ? 'background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;' : 'background:#f0fdf4;border:1px solid #86efac;color:#15803d;'">
            {{ notif.msg }}
        </div>
    </Transition>

    <div style="min-height:100vh;background:#f9fafb;font-family:'Segoe UI',system-ui,sans-serif;">

        <!-- Header -->
        <header style="background:#fff;border-bottom:1px solid #e5e7eb;padding:0 28px;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:22px;">🌮</span>
                <div>
                    <h1 style="font-size:16px;font-weight:800;color:#111827;margin:0;">Ha La Frida</h1>
                    <p style="font-size:11px;color:#16a34a;margin:0;font-weight:700;letter-spacing:0.06em;">ADMINISTRACIÓN</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <div v-if="criticos.length > 0" style="background:#fef3c7;border:1px solid #fbbf24;border-radius:20px;padding:5px 12px;font-size:12px;font-weight:700;color:#92400e;">
                    ⚠️ {{ criticos.length }} críticos
                </div>
                <button @click="router.post('/logout')" style="background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:7px 14px;font-size:13px;font-weight:600;color:#374151;cursor:pointer;">Salir</button>
            </div>
        </header>

        <!-- Tabs -->
        <div style="background:#fff;border-bottom:1px solid #e5e7eb;padding:0 28px;display:flex;gap:2px;overflow-x:auto;">
            <button v-for="t in [['dashboard','📊 Panel'],['inventario','📦 Insumos'],['categorias','🏷️ Categorías'],['menu','🌮 Menú'],['receta', '📋 Recetas'],['usuarios','👥 Usuarios'],['history','📈 Historial'],['profit','📈 Utilidad']]"
                :key="t[0]" @click="tab = t[0]"
                style="padding:14px 18px;border:none;background:transparent;font-size:13px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;font-family:'Segoe UI',sans-serif;transition:all 0.15s;white-space:nowrap;"
                :style="tab===t[0] ? 'color:#16a34a;border-bottom-color:#16a34a;' : 'color:#6b7280;'">
                {{ t[1] }}
            </button>
        </div>

        <div style="padding:24px 28px;max-width:1400px;margin:0 auto;">

            <!-- DASHBOARD -->
            <div v-if="tab==='dashboard'">
                <!-- KPI Cards -->
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
                    <div v-for="card in [
                        { label:'Ventas Hoy', value: '$ '+(parseFloat(stats.ventas_hoy?.Ingresos_Totales||0).toFixed(2)), icon:'💰', color:'#16a34a', bg:'#f0fdf4' },
                        { label:'Facturas', value: stats.ventas_hoy?.Cantidad_Facturas||0, icon:'🧾', color:'#2563eb', bg:'#eff6ff' },
                        { label:'Insumos', value: stats.total_insumos, icon:'📦', color:'#7c3aed', bg:'#f5f3ff' },
                        { label:'Platos Activos', value: stats.total_productos, icon:'🍽️', color:'#d97706', bg:'#fffbeb' },
                    ]" :key="card.label" style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:24px;position:relative;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                        <div :style="`background:${card.bg};color:${card.color}`" style="position:absolute;top:20px;right:20px;width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;">{{ card.icon }}</div>
                        <p style="font-size:12px;color:#6b7280;margin:0 0 10px 0;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">{{ card.label }}</p>
                        <p style="font-size:28px;font-weight:900;margin:0;" :style="`color:#111827`">{{ card.value }}</p>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
                    <!-- Gráfico de Tráfico -->
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                        <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0 0 20px 0;text-transform:uppercase;">📊 Tráfico de Pedidos por Hora</h3>
                        <div style="height:200px;display:flex;align-items:flex-end;gap:12px;padding-bottom:20px;border-bottom:1px solid #f3f4f6;">
                            <div v-for="h in stats.trafico_hora" :key="h.hora" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;">
                                <div :style="`height:${(h.pedidos/Math.max(...stats.trafico_hora.map(x=>x.pedidos)))*100}%`" style="width:100%;background:#16a34a;border-radius:4px 4px 0 0;min-height:4px;transition:height 0.5s ease;"></div>
                                <span style="font-size:10px;font-weight:700;color:#9ca3af;">{{ h.hora }}:00</span>
                            </div>
                            <p v-if="!stats.trafico_hora?.length" style="width:100%;text-align:center;color:#9ca3af;font-size:14px;">Sin datos hoy</p>
                        </div>
                    </div>

                    <!-- Ventas por Categoría -->
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                        <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0 0 20px 0;text-transform:uppercase;">🍕 Ventas por Categoría</h3>
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            <div v-for="c in stats.ventas_por_categoria" :key="c.nombre_cat" style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-size:13px;font-weight:700;color:#4b5563;">{{ c.nombre_cat }}</span>
                                <span style="font-size:13px;font-weight:900;color:#111827;">${{ parseFloat(c.total).toFixed(2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Niveles Críticos -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;margin-top:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="padding:18px 24px;border-bottom:1px solid #fef3c7;background:#fffbeb;">
                        <h2 style="font-size:14px;font-weight:800;color:#92400e;margin:0;display:flex;align-items:center;gap:8px;">⚠️ ALERTAS DE INVENTARIO CRÍTICO</h2>
                    </div>
                    <div style="padding:16px 24px;">
                        <p v-if="stats.inventario_critico?.length===0" style="color:#6b7280;font-size:14px;text-align:center;padding:20px;">Todo el stock está en niveles óptimos ✓</p>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(250px, 1fr));gap:12px;">
                            <div v-for="ins in stats.inventario_critico" :key="ins.id_insumo" style="display:flex;justify-content:space-between;padding:12px;background:#fef2f2;border-radius:12px;border:1px solid #fecaca;">
                                <span style="font-weight:700;font-size:13px;color:#111827;">{{ ins.nombre_insumo }}</span>
                                <span style="color:#dc2626;font-weight:900;">{{ parseFloat(ins.stock_actual).toFixed(2) }} {{ ins.unidad_medida }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INVENTARIO -->
            <div v-if="tab==='inventario'">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                    <h2 style="font-size:18px;font-weight:800;">Gestión de Insumos</h2>
                    <button @click="editInsumoId=null; fInsumo={nombre_insumo:'',unidad_medida:'gramos',stock_actual:0,estado:'Activo'}; modalInsumo=true;" style="background:#16a34a;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;">+ Nuevo Insumo</button>
                </div>
                
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead><tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                            <th style="padding:14px 20px;text-align:left;font-size:11px;color:#6b7280;text-transform:uppercase;">Insumo</th>
                            <th style="padding:14px 20px;text-align:left;font-size:11px;color:#6b7280;text-transform:uppercase;">Stock</th>
                            <th style="padding:14px 20px;text-align:left;font-size:11px;color:#6b7280;text-transform:uppercase;">Estado</th>
                            <th style="padding:14px 20px;text-align:right;font-size:11px;color:#6b7280;text-transform:uppercase;">Acciones</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="ins in insumos" :key="ins.id_insumo" style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:14px 20px;font-weight:700;">{{ ins.nombre_insumo }}</td>
                                <td style="padding:14px 20px;font-weight:800;color:#16a34a;">{{ parseFloat(ins.stock_actual).toFixed(2) }} <span style="font-weight:400;font-size:12px;color:#6b7280;">{{ ins.unidad_medida }}</span></td>
                                <td style="padding:14px 20px;">
                                    <span :style="ins.estado==='Activo'?'background:#dcfce7;color:#16a34a;':'background:#fee2e2;color:#dc2626;'" style="padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;">{{ ins.estado }}</span>
                                </td>
                                <td style="padding:14px 20px;text-align:right;display:flex;justify-content:flex-end;gap:8px;">
                                    <button @click="prepararEditInsumo(ins)" style="background:#f3f4f6;border:none;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">Editar</button>
                                    <button @click="eliminarInsumo(ins.id_insumo)" style="background:#fef2f2;border:none;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;color:#dc2626;cursor:pointer;">Borrar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MENÚ -->
            <div v-if="tab==='menu'">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
                    <div>
                        <h2 style="font-size:18px;font-weight:800;margin-bottom:8px;">Catálogo de Productos</h2>
                        <div style="display:flex;gap:8px;">
                            <button @click="filtroCat=null" :style="filtroCat===null?'background:#000;color:#fff;':'background:#fff;color:#666;'" style="border:1px solid #ddd;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;cursor:pointer;">Todos</button>
                            <button v-for="c in categorias" :key="c.id_categoria" @click="filtroCat=c.id_categoria" :style="filtroCat===c.id_categoria?'background:#000;color:#fff;':'background:#fff;color:#666;'" style="border:1px solid #ddd;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;cursor:pointer;">{{ c.nombre_cat }}</button>
                        </div>
                    </div>
                    <button @click="editProductoId=null; fProducto={nombre_prod:'',precio:'',descripcion:'',id_categoria:'',estado:'Activo'}; modalProducto=true;" style="background:#2563eb;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;">+ Nuevo Platillo</button>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
                    <div v-for="prod in menuFiltrado" :key="prod.id_producto" style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;transition:all 0.2s;" onmouseover="this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)'" onmouseout="this.style.boxShadow='none'">
                        <div style="height:160px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;">
                            <img v-if="prod.url_imagen" :src="prod.url_imagen" style="width:100%;height:100%;object-fit:cover;">
                            <span v-else style="font-size:60px;">🌮</span>
                            <div style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,0.9);padding:4px 10px;border-radius:12px;font-size:12px;font-weight:900;color:#16a34a;">${{ parseFloat(prod.precio).toFixed(2) }}</div>
                        </div>
                        <div style="padding:16px;">
                            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:4px;">
                                <h4 style="font-size:15px;font-weight:800;margin:0;color:#111827;">{{ prod.nombre_prod }}</h4>
                                <span :style="prod.estado==='Activo'?'background:#dcfce7;color:#16a34a;':'background:#fee2e2;color:#dc2626;'" style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;">{{ prod.estado }}</span>
                            </div>
                            <p style="font-size:12px;color:#6b7280;margin:0 0 16px 0;line-height:1.4;height:34px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">{{ prod.descripcion || 'Sin descripción' }}</p>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                <button @click="prepararEditProducto(prod)" style="background:#f3f4f6;border:none;padding:8px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">✏️ Editar</button>
                                <button @click="abrirReceta(prod)" style="background:#eff6ff;border:none;padding:8px;border-radius:8px;font-size:12px;font-weight:700;color:#2563eb;cursor:pointer;">📋 Receta</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECETA -->
            <div v-if="tab==='receta' && productoReceta" style="display:grid;grid-template-columns:340px 1fr;gap:20px;">
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;">
                    <div style="background:#eff6ff;border-radius:10px;padding:12px;margin-bottom:18px;display:flex;align-items:center;gap:12px;">
                        <img v-if="productoReceta.url_imagen" :src="productoReceta.url_imagen" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">
                        <div>
                            <p style="font-size:11px;color:#2563eb;font-weight:800;text-transform:uppercase;margin:0;">Receta para:</p>
                            <p style="font-size:15px;font-weight:900;margin:0;">{{ productoReceta.nombre_prod }}</p>
                        </div>
                    </div>
                    <form @submit.prevent="agregarIngrediente">
                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">{{ editRecetaId ? 'Actualizar' : 'Agregar' }} Insumo</label>
                            <select v-model="fReceta.id_insumo" :disabled="editRecetaId" style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;background:#fff;box-sizing:border-box;">
                                <option v-for="ins in insumos" :key="ins.id_insumo" :value="ins.id_insumo">{{ ins.nombre_insumo }}</option>
                            </select>
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Cantidad</label>
                            <input type="number" step="0.01" v-model="fReceta.cantidad_necesaria" required style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;outline:none;box-sizing:border-box;" />
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button v-if="editRecetaId" type="button" @click="editRecetaId=null; fReceta={id_insumo:'',cantidad_necesaria:''}" style="flex:1;padding:12px;background:#f3f4f6;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Cancelar</button>
                            <button type="submit" style="flex:2;padding:12px;background:#7c3aed;border:none;border-radius:8px;color:#fff;font-weight:700;cursor:pointer;">{{ editRecetaId ? 'Actualizar' : 'Añadir' }}</button>
                        </div>
                    </form>
                </div>

                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead><tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                            <th style="padding:14px 20px;text-align:left;font-size:11px;color:#6b7280;">Ingrediente</th>
                            <th style="padding:14px 20px;text-align:left;font-size:11px;color:#6b7280;">Cantidad</th>
                            <th style="padding:14px 20px;text-align:right;font-size:11px;color:#6b7280;">Acciones</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="r in recetaActual" :key="r.id_receta" style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:14px 20px;font-weight:700;">{{ r.insumo?.nombre_insumo }}</td>
                                <td style="padding:14px 20px;font-weight:800;color:#7c3aed;">{{ r.cantidad_necesaria }} <span style="font-weight:400;font-size:12px;">{{ r.insumo?.unidad_medida }}</span></td>
                                <td style="padding:14px 20px;text-align:right;display:flex;justify-content:flex-end;gap:8px;">
                                    <button @click="prepararEditReceta(r)" style="background:#f3f4f6;border:none;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">Editar</button>
                                    <button @click="eliminarIngrediente(r.id_receta)" style="background:#fef2f2;border:none;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;color:#dc2626;cursor:pointer;">Remover</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CATEGORÍAS -->
            <div v-if="tab==='categorias'">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h2 style="font-size:18px;font-weight:800;">Gestión de Categorías</h2>
                    <button @click="modalCategoria=true" style="background:#7c3aed;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;">+ Nueva Categoría</button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(250px, 1fr));gap:16px;">
                    <div v-for="cat in categorias" :key="cat.id_categoria" style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:20px;display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:45px;height:45px;background:#f5f3ff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;">🏷️</div>
                            <div>
                                <p style="margin:0;font-weight:800;font-size:15px;color:#111827;">{{ cat.nombre_cat }}</p>
                                <p style="margin:2px 0 0 0;font-size:11px;color:#6b7280;">ID: {{ cat.id_categoria }}</p>
                            </div>
                        </div>
                        <button @click="eliminarCategoria(cat.id_categoria)" style="background:#fef2f2;border:none;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;color:#dc2626;cursor:pointer;">Eliminar</button>
                    </div>
                </div>
                <p v-if="!categorias.length" style="text-align:center;color:#9ca3af;padding:40px;font-size:14px;">No hay categorías registradas.</p>
            </div>

            <!-- USUARIOS -->
            <div v-if="tab==='usuarios'">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h2 style="font-size:18px;font-weight:800;">Gestión de Personal</h2>
                    <button @click="modalUsuario=true" style="background:#2563eb;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;">+ Nuevo Empleado</button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:16px;">
                    <div v-for="u in usuarios" :key="u.id_usuario" style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:20px;display:flex;align-items:center;gap:15px;">
                        <div style="width:50px;height:50px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;">👤</div>
                        <div style="flex:1;">
                            <p style="margin:0;font-weight:800;font-size:15px;">{{ u.nombre_completo }}</p>
                            <p style="margin:0;font-size:12px;color:#6b7280;">{{ u.rol?.descripcion }} · {{ u.correo }}</p>
                        </div>
                        <span :style="u.estado==='Activo'?'background:#dcfce7;color:#16a34a;':'background:#fee2e2;color:#dc2626;'" style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:10px;">{{ u.estado }}</span>
                    </div>
                </div>
            </div>

            <!-- PROFIT -->
            <div v-if="tab==='profit'">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:20px;">Análisis de Rentabilidad Histórica</h2>
                <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:24px;margin-bottom:30px;">
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:30px;text-align:center;">
                        <p style="font-size:12px;color:#64748b;font-weight:800;text-transform:uppercase;margin-bottom:10px;">Ingresos Totales (Ventas)</p>
                        <p style="font-size:36px;font-weight:900;color:#16a34a;margin:0;">${{ profitData.ingresos.toFixed(2) }}</p>
                    </div>
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:30px;text-align:center;">
                        <p style="font-size:12px;color:#64748b;font-weight:800;text-transform:uppercase;margin-bottom:10px;">Costos de Producción (Recetas)</p>
                        <p style="font-size:36px;font-weight:900;color:#dc2626;margin:0;">${{ profitData.costos.toFixed(2) }}</p>
                    </div>
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:30px;text-align:center;box-shadow:0 10px 25px rgba(22,163,74,0.1);">
                        <p style="font-size:12px;color:#64748b;font-weight:800;text-transform:uppercase;margin-bottom:10px;">Utilidad Real Bruta</p>
                        <p style="font-size:36px;font-weight:900;color:#2563eb;margin:0;">${{ profitData.utilidad.toFixed(2) }}</p>
                    </div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:40px;text-align:center;">
                    <div style="max-width:600px;margin:0 auto;">
                        <div style="height:40px;background:#f3f4f6;border-radius:20px;overflow:hidden;display:flex;margin-bottom:15px;">
                            <div :style="`width:${(profitData.costos/profitData.ingresos)*100}%`" style="background:#dc2626;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:800;">Costos</div>
                            <div :style="`width:${(profitData.utilidad/profitData.ingresos)*100}%`" style="background:#16a34a;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:800;">Utilidad</div>
                        </div>
                        <p style="font-size:14px;color:#6b7280;font-weight:600;">El margen de utilidad actual es del <b>{{ ((profitData.utilidad/profitData.ingresos)*100).toFixed(1) }}%</b></p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL USUARIO -->
    <Teleport to="body">
        <div v-if="modalUsuario" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:2000;backdrop-filter:blur(4px);">
            <div style="background:#fff;width:100%;max-width:450px;border-radius:24px;padding:32px;box-shadow:0 20px 50px rgba(0,0,0,0.2);">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:20px;">Nuevo Usuario</h2>
                <form @submit.prevent="guardarUsuario">
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Nombre Completo</label>
                        <input type="text" v-model="fUsuario.nombre_completo" required style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:12px;font-size:14px;outline:none;" />
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Correo Electrónico</label>
                        <input type="email" v-model="fUsuario.correo" required style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:12px;font-size:14px;outline:none;" />
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">PIN (4 dígitos)</label>
                            <input type="password" maxlength="4" v-model="fUsuario.pin_acceso" required style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:12px;font-size:14px;outline:none;" />
                        </div>
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Rol</label>
                            <select v-model="fUsuario.id_rol" required style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:12px;font-size:14px;outline:none;background:#fff;">
                                <option value="1">Administrador</option>
                                <option value="2">Mesero</option>
                                <option value="3">Cocinero</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <button type="button" @click="modalUsuario=false" style="padding:14px;background:#f3f4f6;border:none;border-radius:12px;font-weight:700;cursor:pointer;">Cerrar</button>
                        <button type="submit" style="padding:14px;background:#2563eb;border:none;border-radius:12px;color:#fff;font-weight:700;cursor:pointer;">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- MODAL CATEGORÍA -->
    <Teleport to="body">
        <div v-if="modalCategoria" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:2000;backdrop-filter:blur(4px);">
            <div style="background:#fff;width:100%;max-width:400px;border-radius:24px;padding:32px;box-shadow:0 20px 50px rgba(0,0,0,0.2);">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:20px;">Nueva Categoría</h2>
                <form @submit.prevent="guardarCategoria">
                    <div style="margin-bottom:20px;">
                        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Nombre de la Categoría</label>
                        <input type="text" v-model="fCategoria.nombre_cat" required placeholder="Ej: Tacos, Bebidas, Postres..." style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:12px;font-size:14px;outline:none;box-sizing:border-box;" />
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <button type="button" @click="modalCategoria=false" style="padding:14px;background:#f3f4f6;border:none;border-radius:12px;font-weight:700;cursor:pointer;">Cerrar</button>
                        <button type="submit" style="padding:14px;background:#7c3aed;border:none;border-radius:12px;color:#fff;font-weight:700;cursor:pointer;">Crear Categoría</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.slide-enter-active,.slide-leave-active{transition:all .3s}
.slide-enter-from,.slide-leave-to{opacity:0;transform:translateY(-10px)}
</style>
