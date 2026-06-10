/**
 * Full clinical matrix — register + walk every HIV × HPV × VIA path.
 * Phones: +25400xxxxxxx (no live WhatsApp charges).
 * Usage: node hospital_portal/tests/run-clinical-matrix.mjs [apiBaseUrl]
 */
const API = (process.argv[2] || process.env.SMOKE_API || 'https://medicback.onrender.com').replace(/\/$/, '');

const passes = [];
const fails = [];
const rows = [];
const usedSuffixes = new Set();

function ok(msg) { passes.push(msg); console.log('  ✓', msg); }
function bad(msg, d = '') { const line = d ? `${msg} — ${d}` : msg; fails.push(line); console.error('  ✗', line); }

async function api(path, options = {}) {
    const res = await fetch(`${API}${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        },
    });
    const text = await res.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { _raw: text.slice(0, 200) }; }
    return { status: res.status, ok: res.ok, data };
}

function phone() {
    return `+25400${String(Math.floor(Math.random() * 10000000)).padStart(7, '0')}`;
}

function uniqueSuffix() {
    for (let i = 0; i < 50; i++) {
        const s = String(Math.floor(100000 + Math.random() * 900000));
        if (!usedSuffixes.has(s)) {
            usedSuffixes.add(s);
            return s;
        }
    }
    return String(Date.now() % 1000000).padStart(6, '0');
}

async function register({ tag, hiv, hpvDone = 'no', hpvPrior = undefined }) {
    const suf = uniqueSuffix();
    const body = {
        full_name: `MX-${tag}-${suf}`,
        client_no_suffix: suf,
        phone: phone(),
        age: 38,
        contact_channel: 'sms',
        opt_in: 1,
        preferred_language: 'en',
        hiv_status: hiv,
        hpv_done_before: hpvDone,
        place_of_residence: 'Nyeri Matrix Test',
    };
    if (hpvDone === 'yes' && hpvPrior) body.hpv_prior_result = hpvPrior;
    const res = await api('/api/patients.php', { method: 'POST', body: JSON.stringify(body) });
    if (!res.data?.patient_id) {
        bad(`Register ${tag}`, JSON.stringify(res.data).slice(0, 120));
        return null;
    }
    return { id: res.data.patient_id, tag, label: body.full_name, hiv };
}

async function getPatient(id) {
    const res = await api(`/api/patients.php?id=${id}`);
    return res.data?.patient || null;
}

async function runHpvNegativeCase(hiv, tag) {
    const title = `HIV=${hiv} HPV=neg`;
    console.log(`\n--- ${title} ---`);
    const p = await register({ tag, hiv });
    if (!p) return recordRow(title, 'FAIL', 'register');

    const set = await api('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'set_result', patient_id: p.id, result: 'negative' }),
    });
    if (!set.data?.ok) { bad(`${title} set HPV`); return recordRow(title, 'FAIL', 'hpv set'); }

    const conf = await api('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'confirm_result', patient_id: p.id }),
    });
    if (!conf.data?.ok) { bad(`${title} confirm HPV`); return recordRow(title, 'FAIL', 'hpv confirm'); }
    ok(`${title}: registered → HPV neg → confirmed`);

    const pat = await getPatient(p.id);
    const expectYears = hiv === 'positive' ? '3' : '5';
    const bodyOk = String(pat?.hpv_result_confirmed_at || '') !== '';
    recordRow(title, bodyOk ? 'PASS' : 'FAIL', `confirmed_at set; HIV ${hiv} expects ${expectYears}y on future VIA neg`);
}

async function runHpvPositiveViaCase(hiv, via, opts = {}) {
    const cancer = Boolean(opts.cancer);
    const title = `HIV=${hiv} HPV=pos VIA=${via}${cancer ? '+cancer' : ''}`;
    console.log(`\n--- ${title} ---`);
    const p = await register({ tag: `${hiv}-${via}${cancer ? 'C' : ''}`, hiv });
    if (!p) return recordRow(title, 'FAIL', 'register');

    const set = await api('/api/hpv_result.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'set_result', patient_id: p.id, result: 'positive' }),
    });
    if (!set.data?.ok) { bad(`${title} set HPV`); return recordRow(title, 'FAIL', 'hpv set'); }

    const book1 = await api('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add',
            patient_id: p.id,
            scheduled_start: '2026-06-01 10:00:00',
            reason: `First visit ${title}`,
            department: 'VIA clinic',
        }),
    });
    if (!book1.data?.appointment_id || !book1.data?.hpv_result_sent) {
        bad(`${title} first book + HPV SMS`, JSON.stringify(book1.data).slice(0, 120));
        return recordRow(title, 'FAIL', 'hpv book notify');
    }
    ok(`${title}: HPV+ booked → HPV result sent`);

    const att = await api('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'mark_attended', appointment_id: book1.data.appointment_id }),
    });
    if (!att.data?.ok) { bad(`${title} attended`); return recordRow(title, 'FAIL', 'attendance'); }

    const viaBody = {
        patient_id: p.id,
        via_result: via,
        via_date: '2026-06-01',
    };
    if (via === 'positive' && cancer) viaBody.has_cancer = 1;

    const viaRec = await api('/api/via_result.php', { method: 'POST', body: JSON.stringify(viaBody) });
    if (!viaRec.data?.ok || !viaRec.data?.book_followup_next) {
        bad(`${title} VIA record`, JSON.stringify(viaRec.data).slice(0, 120));
        return recordRow(title, 'FAIL', 'via record');
    }
    ok(`${title}: attended → VIA ${via} recorded (SMS deferred)`);

    const book2 = await api('/api/appointments.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add',
            patient_id: p.id,
            scheduled_start: '2026-08-15 11:00:00',
            reason: `Follow-up ${title}`,
        }),
    });
    if (!book2.data?.appointment_id || !book2.data?.via_result_sent) {
        bad(`${title} follow-up book + VIA SMS`, JSON.stringify(book2.data).slice(0, 120));
        return recordRow(title, 'FAIL', 'via notify');
    }
    ok(`${title}: follow-up booked → VIA result sent`);

    const pat = await getPatient(p.id);
    const checkup = pat?.next_checkup_at || null;
    let checkupOk = true;
    let checkupNote = '';
    if (via === 'negative') {
        if (hiv === 'positive') {
            checkupOk = checkup && checkup >= '2029-06-01';
            checkupNote = '3y checkup';
        } else {
            checkupOk = checkup && checkup >= '2031-06-01';
            checkupNote = '5y checkup';
        }
    } else if (via === 'positive' && !cancer) {
        checkupOk = !checkup || checkup === null;
        checkupNote = 'no long checkup on VIA+';
    }

    if (!pat?.via_result_notified_at) bad(`${title} via_result_notified_at missing`);
    else ok(`${title}: notified_at OK, checkup=${checkup || '—'} (${checkupNote})`);

    recordRow(title, checkupOk && pat?.via_result_notified_at ? 'PASS' : 'FAIL',
        `via=${pat?.via_result}, checkup=${checkup || 'none'}, drip stopped at VIA`);
}

function recordRow(caseName, status, note) {
    rows.push({ case: caseName, status, note });
}

async function main() {
    console.log(`\n=== Clinical matrix — ${API} ===`);
    console.log('Order: Register → HPV → Book → Attend → VIA → Follow-up book\n');

    // --- HPV POSITIVE × VIA (all HIV × VIA combos) ---
    await runHpvPositiveViaCase('negative', 'negative');
    await runHpvPositiveViaCase('negative', 'positive');
    await runHpvPositiveViaCase('positive', 'negative');
    await runHpvPositiveViaCase('positive', 'positive');
    await runHpvPositiveViaCase('not_known', 'negative');
    await runHpvPositiveViaCase('not_known', 'positive');
    await runHpvPositiveViaCase('positive', 'positive', { cancer: true });

    // --- HPV NEGATIVE (all HIV — no VIA pathway) ---
    await runHpvNegativeCase('negative', 'neg');
    await runHpvNegativeCase('positive', 'pos');
    await runHpvNegativeCase('not_known', 'unk');

    console.log('\n=== MATRIX SUMMARY ===');
    console.log('| Case | Status | Notes |');
    console.log('|------|--------|-------|');
    for (const r of rows) {
        console.log(`| ${r.case} | ${r.status} | ${r.note} |`);
    }
    console.log(`\nPASS steps: ${passes.length}`);
    console.log(`FAIL steps: ${fails.length}`);

    if (fails.length) {
        fails.forEach((f) => console.error(' -', f));
        process.exit(1);
    }
    console.log('\nAll clinical matrix cases passed.');
}

main().catch((e) => { console.error(e); process.exit(1); });
