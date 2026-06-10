/**
 * Full Afya Rafiki test — document alignment + live API flows.
 * Usage: node hospital_portal/tests/run-full-test.mjs [apiBaseUrl]
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const API = (process.argv[2] || process.env.SMOKE_API || 'https://medicback.onrender.com').replace(/\/$/, '');
const __dir = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dir, '..');

const failures = [];
const passes = [];
const warnings = [];

function pass(msg) {
    passes.push(msg);
    console.log('PASS:', msg);
}

function warn(msg) {
    warnings.push(msg);
    console.warn('WARN:', msg);
}

function fail(msg, detail = '') {
    const line = detail ? `${msg} — ${detail}` : msg;
    failures.push(line);
    console.error('FAIL:', line);
}

async function fetchJson(path, options = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 120000);
    try {
        const res = await fetch(`${API}${path}`, {
            ...options,
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(options.headers || {}),
            },
        });
        const text = await res.text();
        let data = {};
        try {
            data = text ? JSON.parse(text) : {};
        } catch {
            data = { _raw: text.slice(0, 500) };
        }
        return { status: res.status, ok: res.ok, data };
    } finally {
        clearTimeout(timer);
    }
}

function randomPhone() {
    return `+2547${String(Math.floor(Math.random() * 100000000)).padStart(8, '0')}`;
}

/** Unique 6-digit client suffix (avoids NC/NTHC/001/xxx collisions). */
function randomClientSuffix() {
    const tail = String(Date.now() % 1000000).padStart(6, '0');
    const mix = String(Math.floor(Math.random() * 1000)).padStart(3, '0');
    return (tail.slice(0, 3) + mix).slice(0, 6);
}

function randomSuffix() {
    return randomClientSuffix();
}

/** Local static checks against PHP source + markdown doc */
function testLocalDocAlignment() {
    const contentPhp = readFileSync(join(ROOT, 'afya_rafiki_content.php'), 'utf8');
    const doc = readFileSync(join(ROOT, 'docs', 'WHATSAPP_MESSAGE_TEMPLATES.md'), 'utf8');

    const mustExist = [
        ['Welcome to Afya Rafiki, Your Cervical health journey partner', contentPhp, '§1 welcome EN in PHP'],
        ['HPV test result is negative', contentPhp, '§2–3 HPV negative EN in PHP'],
        ['does not mean that you have cervical cancer', contentPhp, '§4 HPV positive EN in PHP'],
        ['appreciate you agreeing to receive messages', contentPhp, '§25 consent thanks in PHP'],
        ['Reminder from Afya Rafiki', contentPhp, '§5–7 reminders in PHP'],
        ['afya_welcome_en', doc, '§1 template name in doc'],
        ['afya_consent_thanks_en', doc, '§25 template name in doc'],
        ['afya_counsel_pos_01', doc, 'Phase 3 counseling in doc'],
    ];
    for (const [needle, hay, label] of mustExist) {
        if (hay.includes(needle)) {
            pass(`Local doc/PHP: ${label}`);
        } else {
            fail(`Local doc/PHP: ${label}`, `missing "${needle.slice(0, 40)}..."`);
        }
    }

    const counselPhp = readFileSync(join(ROOT, 'afya_counseling_positive.php'), 'utf8');
    const enMatches = [...counselPhp.matchAll(/return \[\s*\n((?:\s*'[^']+',?\s*\n)+)/g)];
    if (enMatches.length >= 1) {
        const lines = enMatches[0][1].match(/'/g)?.length ?? 0;
        const count = Math.floor(lines / 2);
        if (count === 15) {
            pass('Local: counseling EN array has 15 messages');
        } else {
            fail('Local: counseling EN array count', `expected 15, approx ${count}`);
        }
    }

    const mtejaPhp = readFileSync(join(ROOT, 'mteja_whatsapp.php'), 'utf8');
    const docTemplates = [
        'afya_welcome', 'afya_hpv_neg_hivpos', 'afya_hpv_neg_hivneg', 'afya_hpv_positive',
        'afya_appt_reminder_7d', 'afya_appt_reminder_3d', 'afya_appt_reminder_1d',
        'afya_via_referral', 'afya_appt_booked', 'afya_help_menu', 'afya_consent_thanks',
        'afya_staff_message', 'afya_ai_reply', 'afya_fallback', 'afya_engagement_tip',
        'afya_appt_updated', 'afya_faq_hpv', 'afya_faq_cancer', 'afya_faq_treat', 'afya_faq_appt',
    ];
    for (const tpl of docTemplates) {
        if (mtejaPhp.includes(`'${tpl}'`)) {
            pass(`Local Mteja maps ${tpl}`);
        } else {
            fail(`Local Mteja maps ${tpl}`, 'not found in mteja_whatsapp.php');
        }
    }

    const messagingPhp = readFileSync(join(ROOT, 'messaging.php'), 'utf8');
    if (messagingPhp.includes('build_consent_thank_you_message') && messagingPhp.includes('build_registration_welcome_message')) {
        pass('Local: registration sends thank-you then welcome');
    } else {
        fail('Local: registration enrollment', 'expected thank-you + registration welcome');
    }
}

async function testSelfTestEndpoint() {
    const res = await fetchJson('/api/afya_self_test.php');
    if (res.status === 404) {
        warn('Server self-test endpoint not deployed yet — push afya_self_test.php and redeploy');
        return;
    }
    if (!res.ok || res.data?.ok === false) {
        const summary = res.data?.summary;
        fail(
            'Server afya_self_test.php',
            summary ? `${summary.failed}/${summary.total} failed` : JSON.stringify(res.data).slice(0, 300)
        );
        if (Array.isArray(res.data?.failures)) {
            for (const f of res.data.failures.slice(0, 8)) {
                fail(`  ↳ ${f.name}`, f.detail || '');
            }
        }
        return;
    }
    pass(`Server afya_self_test.php — ${res.data.summary?.passed}/${res.data.summary?.total} checks`);
    const opt = res.data.results?.find((r) => r.name === 'Optional templates pending Mteja mapping');
    if (opt?.detail && opt.detail.startsWith('missing:')) {
        warn(`Mteja optional templates: ${opt.detail}`);
    }
}

async function testMessagingHealth() {
    const res = await fetchJson('/api/messaging_health.php');
    if (!res.ok || res.data?.ok === false) {
        fail('messaging_health.php', JSON.stringify(res.data).slice(0, 200));
        return;
    }
    pass('messaging_health.php reachable');
    const wa = res.data.channels?.whatsapp;
    const sms = res.data.channels?.sms;
    if (wa) {
        pass(`WhatsApp provider=${wa.provider}, ready=${wa.ready}, verify_token=${wa.verify_token_set ?? 'n/a'}`);
        if (!wa.ready) {
            warn('WhatsApp not ready — outbound WhatsApp will fail until Mteja env + templates are set');
        }
    }
    if (sms) {
        pass(`SMS ready=${sms.ready}`);
    }
    if (Array.isArray(res.data.setup_required) && res.data.setup_required.length) {
        for (const item of res.data.setup_required) {
            warn(`Setup: ${item}`);
        }
    }
}

async function testRegistrationSendsConsentMessage() {
    const suffix = randomSuffix();
    const phone = randomPhone();
    const body = {
        full_name: `TEST AUTO ${suffix}`,
        date_of_birth: '1990-03-15',
        preferred_language: 'en',
        client_no_suffix: suffix,
        phone,
        contact_channel: 'whatsapp',
        opt_in: 1,
        hiv_status: 'negative',
        hpv_done_before: 'no',
        place_of_residence: 'Nyeri Town',
    };

    const created = await fetchJson('/api/patients.php', {
        method: 'POST',
        body: JSON.stringify(body),
    });
    if (!created.ok || !created.data?.patient_id) {
        fail('Register patient with opt_in=1', `${created.status} ${JSON.stringify(created.data).slice(0, 200)}`);
        return null;
    }
    const patientId = created.data.patient_id;
    pass(`Registered test patient id=${patientId} phone=${phone}`);

    const mc = await fetchJson('/api/message_center.php');
    const outbound = (mc.data?.outbound || []).filter((o) => o.full_name?.includes(`TEST AUTO ${suffix}`));
    const thankYou = outbound.find((o) =>
        String(o.body || '').toLowerCase().includes('appreciate you agreeing')
    );
    const regWelcome = outbound.find((o) =>
        String(o.body || '').toLowerCase().includes('remain confidential')
            || String(o.body || '').toLowerCase().includes('itahifadhiwa kwa siri')
    );
    if (thankYou) {
        pass(`Registration thank-you logged — status=${thankYou.status}`);
    } else {
        fail('Registration thank-you message', `found ${outbound.length} outbound row(s) for patient`);
    }
    if (regWelcome) {
        pass(`Registration welcome logged — status=${regWelcome.status}, type=${regWelcome.message_type}`);
        if (regWelcome.status === 'failed') {
            warn(`Welcome failed: ${regWelcome.error_detail || 'unknown'} — check Mteja afya_welcome_en`);
        }
    } else {
        fail('Registration welcome message after thank-you', 'not found in outbound');
    }

    return { patientId, suffix, phone };
}

async function testHpvNegativeFlow(patientId) {
    const set = await fetchJson('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'set_result', patient_id: patientId, result: 'negative' }),
    });
    if (!set.ok || set.data?.ok === false) {
        fail('HPV set_result negative', JSON.stringify(set.data).slice(0, 200));
        return;
    }
    pass('HPV result set to negative');

    const confirm = await fetchJson('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'confirm_result', patient_id: patientId }),
    });
    if (!confirm.ok || confirm.data?.ok === false) {
        fail('HPV confirm negative', JSON.stringify(confirm.data).slice(0, 200));
        return;
    }
    pass('HPV negative confirmed');

    const mc = await fetchJson('/api/message_center.php');
    const hpvMsg = (mc.data?.outbound || []).find(
        (o) =>
            o.full_name?.includes('TEST AUTO') &&
            String(o.body || '').toLowerCase().includes('hpv test result is negative')
    );
    if (hpvMsg) {
        pass(`HPV negative notification logged — status=${hpvMsg.status}`);
        if (hpvMsg.status === 'failed') {
            warn(`HPV negative send failed: ${hpvMsg.error_detail || 'unknown'}`);
        }
    } else {
        fail('HPV negative message in outbound', 'not found in message center');
    }
}

async function testHpvPositiveFullFlow() {
    const suffix = randomClientSuffix();
    const phone = randomPhone();
    const reg = await fetchJson('/api/patients.php', {
        method: 'POST',
        body: JSON.stringify({
            full_name: `TEST HPV+ ${suffix}`,
            client_no_suffix: suffix,
            phone,
            contact_channel: 'sms',
            opt_in: 1,
            preferred_language: 'en',
            hiv_status: 'negative',
            hpv_done_before: 'no',
            place_of_residence: 'Nyeri',
        }),
    });
    if (!reg.data?.patient_id) {
        fail('Register HPV+ test patient', JSON.stringify(reg.data).slice(0, 150));
        return;
    }
    const pid = reg.data.patient_id;
    pass(`Registered HPV+ test patient id=${pid}`);

    await fetchJson('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'set_result', patient_id: pid, result: 'positive' }),
    });

    const confirmNoAppt = await fetchJson('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'confirm_result', patient_id: pid }),
    });
    const err = String(confirmNoAppt.data?.error || '');
    if (confirmNoAppt.data?.ok === false && err.toLowerCase().includes('appointment')) {
        pass('HPV positive blocked without appointment (§4 study flow)');
    } else {
        fail('HPV positive appointment gate', `expected appointment error, got ${JSON.stringify(confirmNoAppt.data).slice(0, 200)}`);
        return;
    }

    const appt = await fetchJson('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add',
            patient_id: pid,
            scheduled_start: '2026-06-14 10:30:00',
            reason: 'HPV positive follow-up VIA',
            department: 'Cervical screening',
            location: 'Nyeri Town Health Centre',
        }),
    });
    if (!appt.ok || !appt.data?.appointment_id) {
        fail('Book appointment for HPV+ patient', JSON.stringify(appt.data).slice(0, 200));
        return;
    }
    pass('Appointment booked for HPV+ patient');

    const confirm = await fetchJson('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'confirm_result', patient_id: pid }),
    });
    if (!confirm.ok || confirm.data?.ok === false) {
        fail('HPV positive confirm with appointment', JSON.stringify(confirm.data).slice(0, 200));
        return;
    }
    pass('HPV positive confirmed — welcome + result per §1 + §4');

    const mc = await fetchJson('/api/message_center.php');
    const rows = (mc.data?.outbound || []).filter((o) => o.full_name?.includes(`TEST HPV+ ${suffix}`));
    const welcome = rows.find((o) => String(o.body || '').includes('Welcome to Afya Rafiki, Your Cervical'));
    const positive = rows.find((o) => String(o.body || '').toLowerCase().includes('hpv test result is positive'));
    if (welcome) {
        pass(`§1 welcome message logged — status=${welcome.status}`);
    } else {
        fail('§1 welcome message after HPV+ confirm', 'not in outbound');
    }
    if (positive) {
        pass(`§4 HPV positive message logged — status=${positive.status}`);
        if (String(positive.body || '').includes('__________')) {
            fail('§4 HPV positive date', 'appointment date still blank');
        }
    } else {
        fail('§4 HPV positive message', 'not in outbound');
    }
}

async function testInboundWebhook(phone, patientLabel) {
    if (!phone) {
        warn('Skip inbound webhook test — no test phone');
        return;
    }
    const digits = phone.replace(/\D/g, '');
    const payload = {
        entry: [{
            changes: [{
                value: {
                    messages: [{
                        from: digits,
                        type: 'text',
                        text: { body: 'HELP' },
                    }],
                },
            }],
        }],
    };
    const res = await fetch(`${API}/webhook_whatsapp.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    const text = await res.text();
    if (res.status !== 200) {
        fail('Inbound webhook POST', `HTTP ${res.status}`);
        return;
    }
    if (text.trim() !== 'OK') {
        fail('Inbound webhook clean response', text.slice(0, 200));
        return;
    }
    pass('Inbound webhook POST accepted (Meta format)');

    await new Promise((r) => setTimeout(r, 2500));
    const mc = await fetchJson('/api/message_center.php');
    const inbound = (mc.data?.inbound || []).find(
        (i) => String(i.from_address || '').includes(digits.slice(-9)) && String(i.body || '').toUpperCase() === 'HELP'
    );
    if (inbound) {
        pass(`Inbound HELP saved — patient=${inbound.full_name || 'unlinked'}`);
    } else {
        warn('Inbound HELP not visible in message_center yet — check WHATSAPP_VERIFY_TOKEN / webhook processing');
    }
}

async function testHpvPositiveRequiresAppointment() {
    /* covered by testHpvPositiveFullFlow */
}

async function testWebhookReachable() {
    const wa = await fetch(`${API}/webhook_whatsapp.php`, { method: 'GET' });
    if (wa.status === 403) {
        pass('webhook_whatsapp.php GET returns 403 without verify token (expected)');
    } else {
        warn(`webhook_whatsapp.php GET status=${wa.status} (403 expected without hub.verify_token)`);
    }
}

async function main() {
    console.log(`\n=== Afya Rafiki full test — ${API} ===\n`);

    testLocalDocAlignment();
    await testSelfTestEndpoint();
    await testMessagingHealth();
    await testWebhookReachable();

    const reg = await testRegistrationSendsConsentMessage();
    if (reg?.patientId) {
        await testHpvNegativeFlow(reg.patientId);
        await testInboundWebhook(reg.phone, reg.suffix);
    }
    await testHpvPositiveFullFlow();

    console.log('\n=== Summary ===');
    console.log(`PASS: ${passes.length}`);
    console.log(`WARN: ${warnings.length}`);
    console.log(`FAIL: ${failures.length}`);
    if (failures.length) {
        console.error('\nFailures:');
        failures.forEach((f) => console.error(' -', f));
        process.exit(1);
    }
    console.log('\nAll critical tests passed.');
    if (warnings.length) {
        console.log('Review warnings above (Mteja templates / env may need attention).');
    }
}

main().catch((e) => {
    console.error(e);
    process.exit(1);
});
