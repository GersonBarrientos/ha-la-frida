<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ auth: { type: Object } });
const tab = ref('dashboard');
const notif = ref(null);
const showNotif = (msg, tipo = 'ok') => { notif.value = { msg, tipo }; setTimeout(() => notif.value = null, 3000); };

// Datos
const stats = ref({ ventas_hoy: { Cantidad_Facturas: 0, Ingresos_Totales: 0 }, inventario_critico: [], total_insumos: 0, total_productos: 0 });
const insumos = ref([]);
const productos = ref([]);
const categorias = ref([]);
const history = ref([]);

// Formularios
const fProd = ref({ nombre_prod: '', precio: '', id_categoria: '', estado: 'Activo' });
const editProdId = ref(null);
const modalProd = ref(false);
const imgFile = ref(null);

const recetaProd = ref(null);
const recetaItems = ref([]);
const fReceta = ref({ id_insumo: '', cantidad_necesaria: '' });

const refresh = async () => {
    try {
        const [s, i, p, c, h] = await Promise.all([
            axios.get('/api/admin/stats'),
            axios.get('/api/admin/insumos'),
            axios.get('/api/admin/productos'),
            axios.get('/api/admin/categorias'),
            axios.get('/api/admin/history'),
        ]);
        stats.value = s.data;
        insumos.value = i.data;
        productos.value = p.data;
        categorias.value = c.data;
        history.value = h.data;
    } catch(e) { console.error('Refresh error'); }
};

onMounted(refresh);

const guardarProducto = async () => {
    const fd = new FormData();
    Object.keys(fProd.value).forEach(k => fd.append(k, fProd.value[k]));
    if (imgFile.value) fd.append('imagen', imgFile.value);

    try {
        if (editProdId.value) {
            fd.append('_method', 'PUT');
            await axios.post(`/api/admin/productos/${editProdId.value}`, fd);
        } else {
            await axios.post('/api/admin/productos', fd);
        }
        showNotif('Producto guardado ✓');
        modalProd.value = false;
        refresh();
    } catch (e) { showNotif('Error al guardar', 'err'); }
};

const abrirReceta = async (p) => {
    recetaProd.value = p;
    const res = await axios.get(`/api/admin/receta/${p.id_producto}`);
    recetaItems.value = res.data;
    tab.value = 'recetas';
};

const addIngrediente = async () => {
    try {
        await axios.post('/api/admin/recetas', { id_producto: recetaProd.value.id_producto, ...fReceta.value });
        const res = await axios.get(`/api/admin/receta/${recetaProd.value.id_producto}`);
        recetaItems.value = res.data;
        fReceta.value = { id_insumo: '', cantidad_necesaria: '' };
        showNotif('Agregado ✓');
    } catch (e) { showNotif('Error', 'err'); }
};

const delIngrediente = async (id) => {
    await axios.delete(`/api/admin/receta/${id}`);
    const res = await axios.get(`/api/admin/receta/${recetaProd.value.id_producto}`);
    recetaItems.value = res.data;
};

const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Panel Admin" />
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">HA LA FRIDA <span>Admin</span></div>
            <nav>
                <button @click="tab='dashboard'" :class="{act: tab==='dashboard'}">📊 Dashboard</button>
                <button @click="tab='productos'" :class="{act: tab==='productos'}">🌮 Productos</button>
                <button @click="tab='receta_grid'" :class="{act: tab==='receta_grid'}">📋 Recetas</button>
                <button @click="tab='historial'" :class="{act: tab==='historial'}">📈 Ventas</button>
            </nav>
            <button @click="logout" class="btn-out">Cerrar Sesión</button>
        </aside>

        <!-- Main -->
        <main class="main">
            <header>
                <h2>{{ tab.toUpperCase() }}</h2>
                <span>{{ auth.user.nombre_completo }}</span>
            </header>

            <div class="content">
                <!-- Dashboard -->
                <div v-if="tab==='dashboard'" class="stats">
                    <div class="s-card">
                        <label>Ventas Hoy</label>
                        <div class="val">$ {{ parseFloat(stats.ventas_hoy?.Ingresos_Totales || 0).toFixed(2) }}</div>
                    </div>
                    <div class="s-card">
                        <label>Stock Crítico</label>
                        <div class="val" style="color:#ef4444">{{ stats.inventario_critico?.length }}</div>
                    </div>
                    <div class="s-card">
                        <label>Productos</label>
                        <div class="val">{{ stats.total_productos }}</div>
                    </div>
                </div>

                <!-- Productos -->
                <div v-if="tab==='productos'" class="section">
                    <button @click="editProdId=null; fProd={nombre_prod:'',precio:'',id_categoria:'',estado:'Activo'}; modalProd=true" class="btn-prim">+ Nuevo</button>
                    <table class="table">
                        <thead><tr><th>Img</th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <tr v-for="p in productos" :key="p.id_producto">
                                <td><img :src="p.url_imagen || '/img/default-food.png'" width="40" height="40" style="object-fit:cover; border-radius:4px;" /></td>
                                <td>{{ p.nombre_prod }}</td>
                                <td>{{ p.categoria?.nombre_cat }}</td>
                                <td>$ {{ p.precio }}</td>
                                <td>
                                    <button @click="editProdId=p.id_producto; fProd={...p}; modalProd=true">Editar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Recetas Grid -->
                <div v-if="tab==='receta_grid'" class="recipe-grid">
                    <div v-for="p in productos" :key="p.id_producto" class="r-card" @click="abrirReceta(p)">
                        <div class="r-icon">🥗</div>
                        <h4>{{ p.nombre_prod }}</h4>
                        <small>{{ p.categoria?.nombre_cat }}</small>
                    </div>
                </div>

                <!-- Editor de Recetas -->
                <div v-if="tab==='recetas'" class="editor">
                    <button @click="tab='receta_grid'">← Volver</button>
                    <h3>Receta: {{ recetaProd?.nombre_prod }}</h3>
                    <div class="r-form">
                        <select v-model="fReceta.id_insumo">
                            <option value="">Insumo...</option>
                            <option v-for="i in insumos" :key="i.id_insumo" :value="i.id_insumo">{{ i.nombre_insumo }}</option>
                        </select>
                        <input type="number" v-model="fReceta.cantidad_necesaria" placeholder="Cant." />
                        <button @click="addIngrediente">Añadir</button>
                    </div>
                    <table class="table">
                        <thead><tr><th>Insumo</th><th>Cantidad</th><th>Acción</th></tr></thead>
                        <tbody>
                            <tr v-for="r in recetaItems" :key="r.id_receta">
                                <td>{{ r.insumo?.nombre_insumo }}</td>
                                <td>{{ r.cantidad_necesaria }} {{ r.insumo?.unidad_medida }}</td>
                                <td><button @click="delIngrediente(r.id_receta)">Eliminar</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Historial -->
                <div v-if="tab==='historial'" class="section">
                    <button @click="window.open('/api/admin/export-daily')" class="btn-sec">📊 Descargar Excel Hoy</button>
                    <table class="table">
                        <thead><tr><th>Factura</th><th>Fecha</th><th>Total</th><th>Mesero</th></tr></thead>
                        <tbody>
                            <tr v-for="h in history" :key="h.id_factura">
                                <td>{{ h.numero_factura }}</td>
                                <td>{{ new Date(h.fecha_pago).toLocaleString() }}</td>
                                <td>$ {{ h.total }}</td>
                                <td>{{ h.pedido?.usuario?.nombre_completo }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Modal Producto -->
        <div v-if="modalProd" class="modal">
            <div class="m-box">
                <h3>{{ editProdId ? 'Editar' : 'Nuevo' }} Producto</h3>
                <input v-model="fProd.nombre_prod" placeholder="Nombre" />
                <input type="number" v-model="fProd.precio" placeholder="Precio ($)" />
                <select v-model="fProd.id_categoria">
                    <option v-for="c in categorias" :key="c.id_categoria" :value="c.id_categoria">{{ c.nombre_cat }}</option>
                </select>
                <input type="file" @change="e => imgFile = e.target.files[0]" />
                <div class="m-btns">
                    <button @click="modalProd=false">Cerrar</button>
                    <button @click="guardarProducto" class="btn-prim">Guardar</button>
                </div>
            </div>
        </div>

        <div v-if="notif" class="toast" :class="notif.tipo">{{ notif.msg }}</div>
    </div>
</template>

<style scoped>
.admin-layout { display: flex; height: 100vh; background: #f8fafc; font-family: sans-serif; color: #1e293b; }
.sidebar { width: 240px; background: #0f172a; color: #fff; padding: 30px 0; display: flex; flex-direction: column; }
.logo { padding: 0 30px 40px; font-weight: bold; font-size: 18px; color: #10b981; }
.logo span { color: #fff; font-weight: normal; opacity: 0.6; }
nav { flex: 1; display: flex; flex-direction: column; }
nav button { background: transparent; border: none; color: #94a3b8; text-align: left; padding: 15px 30px; font-size: 14px; cursor: pointer; }
nav button.act { background: #1e293b; color: #fff; border-left: 4px solid #10b981; }
.btn-out { margin: 20px 30px; padding: 10px; border: 1px solid #ef4444; color: #ef4444; background: transparent; border-radius: 6px; cursor: pointer; }

.main { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
header { padding: 20px 40px; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
.content { padding: 40px; }

.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.s-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; }
.s-card label { font-size: 12px; font-weight: bold; color: #64748b; text-transform: uppercase; }
.s-card .val { font-size: 28px; font-weight: bold; margin-top: 5px; }

.table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; border-radius: 8px; overflow: hidden; }
.table th { background: #f1f5f9; padding: 12px; text-align: left; font-size: 13px; color: #64748b; }
.table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }

.btn-prim { background: #10b981; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; }
.btn-sec { background: #1e293b; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }

.recipe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; }
.r-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; cursor: pointer; }
.r-card:hover { border-color: #10b981; }
.r-icon { font-size: 30px; margin-bottom: 10px; }

.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; }
.m-box { background: #fff; padding: 30px; border-radius: 12px; width: 400px; display: flex; flex-direction: column; gap: 15px; }
.m-box input, .m-box select { padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; }

.toast { position: fixed; bottom: 20px; right: 20px; padding: 12px 25px; border-radius: 8px; color: #fff; font-weight: bold; }
.toast.ok { background: #10b981; }
.toast.err { background: #ef4444; }
</style>
