<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ status: { type: String } });

const form = useForm({ pin_acceso: '' });
const display = ref('');
const shake = ref(false);

const press = (d) => { if (display.value.length < 4) { display.value += d; if (display.value.length === 4) doLogin(); } };
const del = () => { display.value = display.value.slice(0, -1); };
const clear = () => { display.value = ''; };

const doLogin = () => {
    form.pin_acceso = display.value;
    form.post(route('login'), {
        onError: () => { shake.value = true; display.value = ''; setTimeout(() => shake.value = false, 600); },
        onFinish: () => { if (!form.hasErrors) display.value = ''; },
    });
};

const keys = [['1','2','3'],['4','5','6'],['7','8','9'],['C','0','⌫']];
</script>

<template>
    <Head title="Ingresar — Ha La Frida" />

    <div style="min-height:100vh;background:linear-gradient(160deg,#f0fdf4 0%,#f9fafb 60%,#fefce8 100%);display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',system-ui,sans-serif;">

        <div style="width:100%;max-width:340px;padding:20px;">

            <!-- Logo -->
            <div style="text-align:center;margin-bottom:32px;">
                <div style="display:inline-flex;align-items:center;justify-content:center;width:76px;height:76px;background:linear-gradient(135deg,#16a34a,#15803d);border-radius:20px;margin-bottom:14px;box-shadow:0 8px 24px rgba(22,163,74,0.25);">
                    <span style="font-size:36px;">🌮</span>
                </div>
                <h1 style="font-size:26px;font-weight:900;color:#111827;margin:0;letter-spacing:-0.5px;">Ha La Frida</h1>
                <p style="color:#6b7280;font-size:13px;margin:5px 0 0 0;">Santa Ana · Sistema de Gestión</p>
            </div>

            <!-- Card -->
            <div style="background:#fff;border-radius:24px;padding:28px 24px;box-shadow:0 4px 30px rgba(0,0,0,0.09);border:1px solid #f0f0f0;">

                <p style="font-size:13px;font-weight:600;color:#6b7280;text-align:center;margin:0 0 18px 0;text-transform:uppercase;letter-spacing:0.08em;">Ingresa tu PIN</p>

                <!-- Display de 4 dígitos -->
                <div :style="shake ? 'animation:shk 0.5s ease;' : ''" style="display:flex;justify-content:center;gap:12px;margin-bottom:24px;">
                    <div v-for="i in 4" :key="i"
                        style="width:52px;height:60px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:900;transition:all 0.15s;"
                        :style="display.length >= i
                            ? 'background:#f0fdf4;border:2px solid #16a34a;color:#15803d;'
                            : 'background:#f9fafb;border:2px solid #e5e7eb;color:transparent;'">
                        {{ display.length >= i ? '●' : '○' }}
                    </div>
                </div>

                <!-- Error -->
                <p v-if="form.errors.pin_acceso" style="color:#dc2626;font-size:13px;text-align:center;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:8px;margin-bottom:16px;">
                    ⚠️ {{ form.errors.pin_acceso }}
                </p>

                <!-- Teclado numérico -->
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
                    <template v-for="row in keys" :key="row">
                        <button v-for="k in row" :key="k"
                            @click="k==='⌫' ? del() : k==='C' ? clear() : press(k)"
                            style="height:64px;border-radius:12px;font-size:20px;font-weight:700;cursor:pointer;border:1.5px solid;transition:all 0.1s;font-family:'Segoe UI',sans-serif;user-select:none;"
                            :style="k==='C' ? 'background:#fef2f2;border-color:#fca5a5;color:#dc2626;' :
                                    k==='⌫' ? 'background:#fefce8;border-color:#fde68a;color:#92400e;' :
                                    'background:#f9fafb;border-color:#e5e7eb;color:#111827;'"
                            @mousedown="$event.currentTarget.style.transform='scale(0.94)'"
                            @mouseup="$event.currentTarget.style.transform='scale(1)'"
                            @touchstart.prevent="k==='⌫' ? del() : k==='C' ? clear() : press(k)">
                            {{ k }}
                        </button>
                    </template>
                </div>

                <!-- Botón confirmar -->
                <button @click="doLogin" :disabled="form.processing || display.length < 4"
                    style="margin-top:14px;width:100%;height:52px;border:none;border-radius:12px;font-size:15px;font-weight:800;cursor:pointer;letter-spacing:0.04em;font-family:'Segoe UI',sans-serif;transition:all 0.2s;"
                    :style="(form.processing || display.length < 4)
                        ? 'background:#f3f4f6;color:#9ca3af;cursor:not-allowed;'
                        : 'background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;box-shadow:0 4px 14px rgba(22,163,74,0.3);'">
                    {{ form.processing ? 'Verificando...' : '✓ Ingresar' }}
                </button>
            </div>

            <!-- Roles -->
            <div style="margin-top:18px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
                <div v-for="[ico,label] in [['👨‍💼','Admin'],['🤵','Mesero'],['👨‍🍳','Cocinero']]" :key="label"
                    style="background:rgba(255,255,255,0.8);border:1px solid #e5e7eb;border-radius:10px;padding:10px;text-align:center;">
                    <div style="font-size:18px;">{{ ico }}</div>
                    <p style="font-size:11px;color:#6b7280;margin:4px 0 0 0;font-weight:600;">{{ label }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@keyframes shk {
    0%,100%{transform:translateX(0)}
    20%{transform:translateX(-10px)}
    40%{transform:translateX(10px)}
    60%{transform:translateX(-8px)}
    80%{transform:translateX(8px)}
}
</style>
