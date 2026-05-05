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
const productos = ref([]);
const categorias = ref([]);
const usuarios = ref([]);
const history = ref([]);

const fInsumo = ref({ nombre_insumo: '', unidad_medida: 'gramos', stock_actual: 0, estado: 'Activo' });
const modalInsumo = ref(false);
const editInsumoId = ref(null);

const fUsuario = ref({ nombre_completo: '', correo: '', pin_acceso: '', id_rol: '' });
const modalUsuario = ref(false);

const fCategoria = ref({ nombre_cat: '' });
const modalCategoria = ref(false);

const fProducto = ref({ nombre_prod: '', precio: '', descripcion: '', id_categoria: '', estado: 'Activo' });
const productoImagen = ref(null);
const modalProducto = ref(false);
const editProductoId = ref(null);

const productoReceta = ref(null);
const recetaActual = ref([]);
const fReceta = ref({ id_insumo: '', cantidad_necesaria: '' });

const refreshAll = async () => {
    try {
        const [s, ins, prod, cats, usr, hist] = await Promise.all([
            axios.get('/api/admin/stats'),
            axios.get('/api/admin/insumos'),
            axios.get('/api/admin/productos'),
            axios.get('/api/admin/categorias'),
            axios.get('/api/admin/usuarios'),
            axios.get('/api/admin/history'),
        ]);
        stats.value = s.data;
        insumos.value = ins.data;
        productos.value = prod.data;
        categorias.value = cats.data;
        usuarios.value = usr.data;
        history.value = hist.data;
    } catch(e) { console.error('Error loading data:', e); }
};

onMounted(refreshAll);

const guardarProducto = async () => {
    const fd = new FormData();
    Object.keys(fProducto.value).forEach(k => fd.append(k, fProducto.value[k]));
    if (productoImagen.value) fd.append('imagen', productoImagen.value);

    try {
        if (editProductoId.value) {
            fd.append('_method', 'PUT');
            await axios.post(`/api/admin/productos/${editProductoId.value}`, fd);
            showNotif('Producto actualizado ✓');
        } else {
            await axios.post('/api/admin/productos', fd);
            showNotif('Producto registrado ✓');
        }
        modalProducto.value = false;
        await refreshAll();
    } catch (e) { showNotif('Error al guardar producto', 'err'); }
};

const editarReceta = async (prod) => {
    productoReceta.value = prod;
    const res = await axios.get(`/api/admin/receta/${prod.id_producto}`);
    recetaActual.value = res.data;
    tab.value = 'receta_editor';
};

const agregarIngrediente = async () => {
    try {
        await axios.post('/api/admin/recetas', {
            id_producto: productoReceta.value.id_producto,
            id_insumo: fReceta.value.id_insumo,
            cantidad_necesaria: fReceta.value.cantidad_necesaria
        });
        const res = await axios.get(`/api/admin/receta/${productoReceta.value.id_producto}`);
        recetaActual.value = res.data;
        fReceta.value = { id_insumo: '', cantidad_necesaria: '' };
        showNotif('Ingrediente añadido');
    } catch (e) { showNotif('Error al añadir ingrediente', 'err'); }
};

const eliminarIngrediente = async (id) => {
    await axios.delete(`/api/admin/receta/${id}`);
    const res = await axios.get(`/api/admin/receta/${productoReceta.value.id_producto}`);
    recetaActual.value = res.data;
};

const exportCSV = () => {
    window.open('/api/admin/export-daily', '_blank');
};

const logout = () => router.post(route('logout'));

// Helpers
const getImgUrl = (path) => path ? path : '/img/default-food.png';
</script>

<template>
    <Head title="Admin Dashboard" />
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <nav class="admin-nav">
            <div class="nav-brand">HA LA FRIDA <span>Admin</span></div>
            <div class="nav-items">
                <button v-for="t in [['dashboard','📊 Dashboard'],['menu','🌮 Productos'],['categorias','🏷️ Categorías'],['recetas','📋 Recetas'],['inventario','📦 Insumos'],['history','📈 Historial'],['usuarios','👥 Usuarios']]" 
                    :key="t[0]" @click="tab = t[0]" :class="{active: tab === t[0]}">
                    {{ t[1] }}
                </button>
            </div>
            <button @click="logout" class="nav-logout">Cerrar Sesión</button>
        </nav>

        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <h2>{{ tab.charAt(0).toUpperCase() + tab.slice(1) }}</h2>
                <div class="admin-user">Admin: {{ auth.user.nombre_completo }}</div>
            </header>

            <div class="admin-content">
                <!-- DASHBOARD -->
                <div v-if="tab === 'dashboard'" class="fade-in">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <span>Ventas Hoy</span>
                            <div class="value">$ {{ parseFloat(stats.ventas_hoy?.Ingresos_Totales || 0).toFixed(2) }}</div>
                            <div class="sub">{{ stats.ventas_hoy?.Cantidad_Facturas }} pedidos</div>
                        </div>
                        <div class="stat-card">
                            <span>Insumos Críticos</span>
                            <div class="value" :style="{color: stats.inventario_critico?.length > 0 ? 'red' : 'green'}">{{ stats.inventario_critico?.length }}</div>
                            <div class="sub">Stock menor a 10</div>
                        </div>
                        <div class="stat-card">
                            <span>Total Productos</span>
                            <div class="value">{{ stats.total_productos }}</div>
                            <div class="sub">En catálogo activo</div>
                        </div>
                    </div>
                    
                    <div class="charts-row">
                        <div class="chart-box">
                            <h3>⚠️ Alerta de Inventario</h3>
                            <table class="modern-table">
                                <thead><tr><th>Insumo</th><th>Stock</th></tr></thead>
                                <tbody>
                                    <tr v-for="i in stats.inventario_critico" :key="i.id_insumo">
                                        <td>{{ i.nombre_insumo }}</td>
                                        <td style="color:red; font-weight:800;">{{ i.stock_actual }} {{ i.unidad_medida }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MENU / PRODUCTOS -->
                <div v-if="tab === 'menu'" class="fade-in">
                    <div class="action-bar">
                        <button @click="editProductoId=null; fProducto={nombre_prod:'',precio:'',descripcion:'',id_categoria:'',estado:'Activo'}; modalProducto=true" class="btn-add">+ Nuevo Producto</button>
                    </div>
                    <div class="products-grid">
                        <div v-for="p in productos" :key="p.id_producto" class="admin-prod-card">
                            <img :src="getImgUrl(p.url_imagen)" alt="prod" class="prod-thumb" />
                            <div class="prod-body">
                                <h4>{{ p.nombre_prod }}</h4>
                                <p class="prod-cat">{{ p.categoria?.nombre_cat }}</p>
                                <span class="prod-price">$ {{ p.precio }}</span>
                                <div class="prod-actions">
                                    <button @click="editProductoId=p.id_producto; fProducto={...p}; modalProducto=true" class="btn-edit">Editar</button>
                                    <button @click="editarReceta(p)" class="btn-recipe">Receta</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RECETAS (Grid View) -->
                <div v-if="tab === 'recetas'" class="fade-in">
                    <h3>Gestión de Recetas</h3>
                    <p>Selecciona un producto para editar su fórmula de ingredientes.</p>
                    <div class="recipe-grid">
                        <div v-for="p in productos" :key="p.id_producto" class="recipe-item" @click="editarReceta(p)">
                            <div class="recipe-icon">🥣</div>
                            <h4>{{ p.nombre_prod }}</h4>
                            <span>{{ p.categoria?.nombre_cat }}</span>
                        </div>
                    </div>
                </div>

                <!-- RECETA EDITOR -->
                <div v-if="tab === 'receta_editor'" class="fade-in">
                    <button @click="tab='recetas'" class="btn-back">← Volver a Recetas</button>
                    <div class="editor-box">
                        <h3>Editando Receta: {{ productoReceta.nombre_prod }}</h3>
                        <div class="editor-form">
                            <select v-model="fReceta.id_insumo" class="modern-input">
                                <option value="">Seleccionar Insumo</option>
                                <option v-for="i in insumos" :key="i.id_insumo" :value="i.id_insumo">{{ i.nombre_insumo }}</option>
                            </select>
                            <input type="number" v-model="fReceta.cantidad_necesaria" placeholder="Cantidad" class="modern-input" />
                            <button @click="agregarIngrediente" class="btn-add">Añadir</button>
                        </div>
                        <table class="modern-table">
                            <thead><tr><th>Ingrediente</th><th>Cant. Necesaria</th><th>Unidad</th><th>Acción</th></tr></thead>
                            <tbody>
                                <tr v-for="r in recetaActual" :key="r.id_receta">
                                    <td>{{ r.insumo?.nombre_insumo }}</td>
                                    <td>{{ r.cantidad_necesaria }}</td>
                                    <td>{{ r.insumo?.unidad_medida }}</td>
                                    <td><button @click="eliminarIngrediente(r.id_receta)" class="btn-del">❌</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- HISTORY / REPORTS -->
                <div v-if="tab === 'history'" class="fade-in">
                    <div class="action-bar">
                        <h3>Historial de Ventas</h3>
                        <button @click="exportCSV" class="btn-export">📊 Exportar Hoy (Excel/CSV)</button>
                    </div>
                    <div class="table-container">
                        <table class="modern-table">
                            <thead><tr><th>No. Factura</th><th>Fecha</th><th>Total</th><th>Método</th><th>Mesero</th></tr></thead>
                            <tbody>
                                <tr v-for="h in history" :key="h.id_factura">
                                    <td>{{ h.numero_factura }}</td>
                                    <td>{{ new Date(h.fecha_pago).toLocaleString() }}</td>
                                    <td class="bold">$ {{ h.total }}</td>
                                    <td>{{ h.metodo_pago }}</td>
                                    <td>{{ h.pedido?.usuario?.nombre_completo }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <!-- MODAL PRODUCTO -->
        <div v-if="modalProducto" class="modal-overlay">
            <div class="modal-content">
                <h3>{{ editProductoId ? 'Editar Producto' : 'Nuevo Producto' }}</h3>
                <div class="form-grid">
                    <input v-model="fProducto.nombre_prod" placeholder="Nombre" class="modern-input" />
                    <input type="number" v-model="fProducto.precio" placeholder="Precio ($)" class="modern-input" />
                    <select v-model="fProducto.id_categoria" class="modern-input">
                        <option value="">Categoría...</option>
                        <option v-for="c in categorias" :key="c.id_categoria" :value="c.id_categoria">{{ c.nombre_cat }}</option>
                    </select>
                    <textarea v-model="fProducto.descripcion" placeholder="Descripción" class="modern-input"></textarea>
                    <input type="file" @change="e => productoImagen = e.target.files[0]" class="modern-input" />
                </div>
                <div class="modal-actions">
                    <button @click="modalProducto=false" class="btn-sec">Cerrar</button>
                    <button @click="guardarProducto" class="btn-prim">Guardar</button>
                </div>
            </div>
        </div>

        <div v-if="notif" class="toast" :class="notif.tipo">{{ notif.msg }}</div>
    </div>
</template>

<style scoped>
.admin-container { display: flex; height: 100vh; background: #f1f5f9; }

/* Sidebar */
.admin-nav { width: 260px; background: #0f172a; color: #fff; padding: 30px 0; display: flex; flex-direction: column; }
.nav-brand { padding: 0 30px 40px; font-size: 20px; font-weight: 800; color: #10b981; }
.nav-brand span { color: #fff; font-size: 14px; opacity: 0.7; }
.nav-items { flex: 1; display: flex; flex-direction: column; gap: 5px; }
.nav-items button { background: transparent; border: none; color: #94a3b8; padding: 15px 30px; text-align: left; font-size: 15px; font-weight: 600; cursor: pointer; transition: 0.3s; }
.nav-items button:hover, .nav-items button.active { background: #1e293b; color: #fff; border-left: 4px solid #10b981; }
.nav-logout { margin: 20px 30px; padding: 12px; border-radius: 10px; border: 1px solid #334155; background: transparent; color: #ef4444; cursor: pointer; }

/* Main */
.admin-main { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
.admin-header { padding: 20px 40px; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
.admin-content { padding: 40px; }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 40px; }
.stat-card { background: #fff; padding: 25px; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.stat-card span { font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase; }
.stat-card .value { font-size: 32px; font-weight: 800; margin: 10px 0; }
.stat-card .sub { font-size: 12px; color: #94a3b8; }

/* Product Grid */
.products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
.admin-prod-card { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.prod-thumb { width: 100%; height: 140px; object-fit: cover; }
.prod-body { padding: 20px; }
.prod-body h4 { margin: 0; font-size: 16px; }
.prod-cat { font-size: 12px; color: #64748b; margin: 5px 0 10px; }
.prod-price { font-weight: 800; color: #10b981; font-size: 18px; }
.prod-actions { margin-top: 15px; display: flex; gap: 8px; }

/* Recipe Grid */
.recipe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
.recipe-item { background: #fff; padding: 20px; border-radius: 20px; text-align: center; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
.recipe-item:hover { border-color: #10b981; transform: translateY(-5px); }
.recipe-icon { font-size: 32px; margin-bottom: 10px; }

/* Modern Table */
.modern-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 15px; overflow: hidden; }
.modern-table th { background: #f8fafc; padding: 15px; text-align: left; font-size: 12px; color: #64748b; text-transform: uppercase; }
.modern-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
.bold { font-weight: 800; }

.modern-input { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 15px; box-sizing: border-box; }

.btn-add, .btn-prim { background: #10b981; color: #fff; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; }
.btn-edit { background: #f1f5f9; border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer; }
.btn-recipe { background: #6366f1; color: #fff; border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer; }
.btn-export { background: #0f172a; color: #fff; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; }

.toast { position: fixed; bottom: 30px; right: 30px; padding: 15px 30px; border-radius: 10px; color: #fff; font-weight: 700; }
.toast.ok { background: #10b981; }
.toast.err { background: #ef4444; }

.fade-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: #fff; padding: 40px; border-radius: 25px; width: 450px; }
</style>
