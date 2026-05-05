const fs = require('fs');

// Fix Admin Dashboard
let admin = fs.readFileSync('resources/js/Pages/Admin/Dashboard.vue', 'utf8');
admin = admin.replace(/Costo Unitario \(Q\)/g, 'Costo Unitario ($)');
admin = admin.replace(/Precio \(Q\)/g, 'Precio ($)');
admin = admin.replace(/Q\{\{/g, '$' + '{{');
admin = admin.replace(/>Q/g, '>$');
admin = admin.replace(/'Q '/g, "'$ '");
fs.writeFileSync('resources/js/Pages/Admin/Dashboard.vue', admin);

// Fix Mesero Dashboard
let mesero = fs.readFileSync('resources/js/Pages/Mesero/Dashboard.vue', 'utf8');
mesero = mesero.replace(/Q\{\{/g, '$' + '{{');
mesero = mesero.replace(/>Q/g, '>$');
mesero = mesero.replace(/'Q '/g, "'$ '");
// Remove nombre_cliente and nit_cliente from order submission
mesero = mesero.replace(/nombre_cliente: clienteNombre\.value,\n\s*nit_cliente: clienteNit\.value,\n/, '');
fs.writeFileSync('resources/js/Pages/Mesero/Dashboard.vue', mesero);

console.log('Currency fixed in both dashboards!');
