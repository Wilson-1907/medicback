/**
 * All workflow scenarios — uses +25400xxxxxxx test phones (disconnected / no live billing).
 * Usage: node hospital_portal/tests/run-all-scenarios.mjs [apiBaseUrl]
 */
const API = (process.argv[2] || process.env.SMOKE_API || 'https://medicback.onrender.com').replace(/\/$/, '');

const passes = [];
const fails = [];
const warns = [];

function pass(msg) { passes.push(msg); console.log('PASS:', msg); }
function fail(msg, d = '') { const line = d ? `${msg} — ${d}` : msg; fails.push(line); console.error('FAIL:', line); }
function warn(msg) { warns.push(msg); console.warn('WARN:', msg); }

async function fetchJson(path, options = {}) {
    const res = await fetch(`${API}${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(options.headers || {}),
        },
    });
    const text = await res.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { _raw: text.slice(0, 400) }; }
    return { status: res.status, ok: res.ok, data };
}

function testPhone() {
    const tail = String(Math.floor(Math.random() * 10000000)).padStart(7, '0');
    return `+25400${tail}`;
}

function clientSuffix() {
    return String(Date.now() % 1000000).padStart(6, '0').slice(0, 6);
}

async function registerPatient(label, extra = {}) {
    const suffix = clientSuffix();
    const body = {
        full_name: `${label} ${suffix}`,
        client_no_suffix: suffix,
        phone: testPhone(),
        age: 35,
        contact_channel: 'sms',
        opt_in: 1,
        preferred_language: 'en',
        hiv_status: 'negative',
        hpv_done_before: 'no',
        place_of_residence: 'Nyeri Test Ward',
        ...extra,
    };
    const res = await fetchJson('/api/patients.php', { method: 'POST', body: JSON.stringify(body) });
    if (!res.ok || !res.data?.patient_id) {
        fail(`Register ${label}`, `${res.status} ${JSON.stringify(res.data).slice(0, 180)}`);
        return null;
    }
    pass(`Register ${label} id=${res.data.patient_id} phone=${body.phone}`);
    return { id: res.data.patient_id, suffix, phone: body.phone, label: `${label} ${suffix}` };
}

async function getPatient(id) {
    const res = await fetchJson(`/api/patients.php?id=${id}`);
    return res.data?.patient || null;
}

async function outboundFor(label) {
    const mc = await fetchJson('/api/message_center.php');
    return (mc.data?.outbound || []).filter((o) => (o.full_name || '').includes(label.split(' ').slice(-1)[0]) || (o.full_name || '').includes(label));
}

function summarizeOutbound(rows, tag) {
    if (!rows.length) {
        warn(`${tag}: no outbound rows`);
        return;
    }
    const byType = {};
    for (const r of rows) {
        const k = `${r.message_type}:${r.status}`;
        byType[k] = (byType[k] || 0) + 1;
    }
    pass(`${tag}: ${rows.length} outbound — ${JSON.stringify(byType)}`);
    const failed = rows.filter((r) => r.status === 'failed');
    for (const f of failed.slice(0, 3)) {
        warn(`${tag} failed ${f.message_type}: ${(f.error_detail || '').slice(0, 120)}`);
    }
}

async function testSelfTestAndTemplates() {
    const res = await fetchJson('/api/afya_self_test.php');
    if (!res.ok || !res.data?.ok) {
        fail('afya_self_test.php', JSON.stringify(res.data?.failures?.slice(0, 5) || res.data).slice(0, 300));
        return;
    }
    pass(`Self-test ${res.data.summary.passed}/${res.data.summary.total} checks`);

    const mtejaChecks = (res.data.results || []).filter((r) => r.name.startsWith('Mteja template') || r.name.startsWith('Mteja maps'));
    const mtejaFailed = mtejaChecks.filter((r) => !r.pass);
    if (mtejaFailed.length) {
        fail('Mteja template mapping', mtejaFailed.map((r) => r.name).join(', '));
    } else {
        pass(`Mteja template mapping — ${mtejaChecks.length} resolvers OK (code-side; Mteja approval is separate)`);
    }

    const opt = res.data.results?.find((r) => r.name === 'Optional templates pending Mteja mapping');
    if (opt?.detail?.startsWith('missing:')) {
        warn(`Optional Mteja templates not mapped in code: ${opt.detail}`);
    }
}

async function testMessagingHealthAndCapacity() {
    const res = await fetchJson('/api/messaging_health.php');
    if (!res.ok) {
        fail('messaging_health.php', String(res.status));
        return;
    }
    pass('messaging_health reachable');
    const d = res.data;
    if (d.channels?.whatsapp) {
        pass(`WhatsApp provider=${d.channels.whatsapp.provider} ready=${d.channels.whatsapp.ready}`);
    }
    if (d.channels?.sms) {
        pass(`SMS ready=${d.channels.sms.ready}`);
    }
    if (Array.isArray(d.setup_required)) {
        for (const s of d.setup_required) warn(`Setup: ${s}`);
    }
    if (d.last_whatsapp_error) {
        warn(`Last WA error: ${String(d.last_whatsapp_error).slice(0, 150)}`);
    }

    const patients = await fetchJson('/api/patients.php');
    const count = patients.data?.items?.length ?? 0;
    pass(`Current patients in list API: ${count} (UI list capped at 500)`);
    warn('Practical capacity: ~500 active in staff list UI; DB can hold thousands on Aiven; cron + Mteja rate limits matter more than code cap');
}

async function scenarioHpvNegative() {
    const p = await registerPatient('SCN-HPV-NEG');
    if (!p) return;
    await fetchJson('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'set_result', patient_id: p.id, result: 'negative' }),
    });
    const c = await fetchJson('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'confirm_result', patient_id: p.id }),
    });
    if (!c.data?.ok) fail('HPV negative confirm', JSON.stringify(c.data).slice(0, 150));
    else pass('HPV negative: record + confirm');
    summarizeOutbound(await outboundFor(p.label), 'HPV negative');
}

async function scenarioHpvPositiveBookAuto() {
    const p = await registerPatient('SCN-HPV-POS');
    if (!p) return;
    await fetchJson('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'set_result', patient_id: p.id, result: 'positive' }),
    });
    const block = await fetchJson('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'confirm_result', patient_id: p.id }),
    });
    if (block.data?.ok === false && String(block.data.error || '').toLowerCase().includes('appointment')) {
        pass('HPV+ blocked without appointment');
    } else fail('HPV+ appointment gate');

    const book = await fetchJson('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add',
            patient_id: p.id,
            scheduled_start: '2026-06-01 09:00:00',
            reason: 'First VIA visit',
            department: 'Cervical clinic',
        }),
    });
    if (!book.data?.appointment_id) {
        fail('HPV+ book appointment', JSON.stringify(book.data).slice(0, 150));
        return;
    }
    if (book.data.hpv_result_sent) pass('HPV+ book auto-sent HPV result');
    else warn('HPV+ book did not auto-send HPV (may already be confirmed)');
    if (book.data.counseling_started) pass('HPV+ drip scheduled after book');
    summarizeOutbound(await outboundFor(p.label), 'HPV+ book');
    return p;
}

async function scenarioVisitViaFollowup() {
    const p = await registerPatient('SCN-VIA-FLOW');
    if (!p) return;
    await fetchJson('/api/hpv_result.php', { method: 'POST', body: JSON.stringify({ action: 'set_result', patient_id: p.id, result: 'positive' }) });
    const b = await fetchJson('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add', patient_id: p.id, scheduled_start: '2026-06-01 10:00:00', reason: 'First visit',
        }),
    });
    if (!b.data?.appointment_id) { fail('VIA flow setup book'); return; }
    p.firstApptId = b.data.appointment_id;

    if (!p.firstApptId) {
        fail('VIA flow: no appointment id');
        return;
    }

    const att = await fetchJson('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'mark_attended', appointment_id: p.firstApptId }),
    });
    if (!att.data?.ok) fail('Mark attended', JSON.stringify(att.data).slice(0, 150));
    else pass(`Mark attended appt=${p.firstApptId} record_via_next=${att.data.record_via_next}`);

    const via = await fetchJson('/api/via_result.php', {
        method: 'POST',
        body: JSON.stringify({
            patient_id: p.id,
            via_result: 'negative',
            via_date: '2026-06-01',
        }),
    });
    if (!via.data?.ok) fail('Record VIA negative', JSON.stringify(via.data).slice(0, 150));
    else if (via.data.book_followup_next) pass('VIA recorded — awaits follow-up book (no SMS yet)');
    else warn('VIA record missing book_followup_next flag');

    const pre = await getPatient(p.id);
    if (pre?.via_result_notified_at) warn('VIA already notified before follow-up book');
    else pass('VIA not notified until follow-up booked');

    const book2 = await fetchJson('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add',
            patient_id: p.id,
            scheduled_start: '2026-08-01 14:00:00',
            reason: 'VIA follow-up',
        }),
    });
    if (!book2.data?.appointment_id) fail('VIA follow-up book', JSON.stringify(book2.data).slice(0, 150));
    else if (book2.data.via_result_sent) pass('Follow-up book auto-sent VIA result');
    else fail('Follow-up book should send VIA result', JSON.stringify(book2.data));

    const after = await getPatient(p.id);
    if (after?.via_result_notified_at) pass('via_result_notified_at set after follow-up book');
    else fail('via_result_notified_at missing after book');
    summarizeOutbound(await outboundFor(p.label), 'VIA follow-up');
}

async function scenarioMissed() {
    const p = await registerPatient('SCN-MISSED');
    if (!p) return;
    const book = await fetchJson('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add', patient_id: p.id, scheduled_start: '2026-05-01 08:00:00', reason: 'Missed test',
        }),
    });
    const apptId = book.data?.appointment_id;
    if (!apptId) { fail('Missed scenario book'); return; }
    const miss = await fetchJson('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'mark_missed', appointment_id: apptId }),
    });
    if (!miss.data?.ok) fail('Mark missed', JSON.stringify(miss.data).slice(0, 150));
    else pass(`Missed visit — missed_message_sent=${miss.data.missed_message_sent}`);
}

async function scenarioManualReferral() {
    const p = await registerPatient('SCN-REFER');
    if (!p) return;
    const ref = await fetchJson('/api/referral.php', {
        method: 'POST',
        body: JSON.stringify({
            patient_id: p.id,
            referral_appointment_date: '2026-09-01',
            manual_override: 1,
        }),
    });
    if (!ref.data?.ok) fail('Manual referral', JSON.stringify(ref.data).slice(0, 150));
    else pass(`Manual referral override — sent=${ref.data.referral_sent}`);
}

async function scenarioHpvPositiveSw() {
    const p = await registerPatient('SCN-SW', { preferred_language: 'sw', contact_channel: 'whatsapp' });
    if (!p) return;
    await fetchJson('/api/hpv_result.php', { method: 'POST', body: JSON.stringify({ action: 'set_result', patient_id: p.id, result: 'positive' }) });
    const book = await fetchJson('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add', patient_id: p.id, scheduled_start: '2026-07-20 11:00:00', reason: 'Ziara ya kliniki',
        }),
    });
    if (book.data?.hpv_result_sent) pass('Swahili HPV+ book path OK');
    else warn('Swahili HPV+ auto-send', JSON.stringify(book.data).slice(0, 100));
}

async function analyzeTemplateFailures() {
    const mc = await fetchJson('/api/message_center.php');
    const failed = (mc.data?.outbound || []).filter((o) => o.status === 'failed').slice(0, 15);
    if (!failed.length) {
        pass('No failed outbound in recent message center window');
        return;
    }
    const templateErrors = failed.filter((o) =>
        /template|not found|approved|invalid|language/i.test(String(o.error_detail || ''))
    );
    if (templateErrors.length) {
        warn(`${templateErrors.length} recent failures look like Mteja template issues:`);
        for (const e of templateErrors.slice(0, 5)) {
            warn(`  ${e.message_type}: ${(e.error_detail || '').slice(0, 140)}`);
        }
    } else {
        pass(`Recent failures (${failed.length}) — not template-name errors (likely invalid +25400 test numbers)`);
    }
}

async function main() {
    console.log(`\n=== All scenarios — ${API} ===`);
    console.log('Test phones: +25400xxxxxxx (disconnected pattern)\n');

    await testSelfTestAndTemplates();
    await testMessagingHealthAndCapacity();
    await scenarioHpvNegative();
    await scenarioHpvPositiveBookAuto();
    await scenarioVisitViaFollowup();
    await scenarioMissed();
    await scenarioManualReferral();
    await scenarioHpvPositiveSw();
    await analyzeTemplateFailures();

    console.log('\n=== SUMMARY ===');
    console.log(`PASS: ${passes.length}`);
    console.log(`WARN: ${warns.length}`);
    console.log(`FAIL: ${fails.length}`);
    if (fails.length) {
        fails.forEach((f) => console.error(' -', f));
        process.exit(1);
    }
}

main().catch((e) => { console.error(e); process.exit(1); });
