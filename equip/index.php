<?php
$conn = new mysqli("localhost", "root", "", "equipbase");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Perhitungan SAW - SPK Equipment</title>
  <link rel="stylesheet" href="style.css?v=2">
</head>
<body>
  <div class="container">
    <h1>Simple Additive Weigthing</h1>
    <h3>Pemilihan Prioritas Equipment</h3>

    <div class="form-section">
      <!-- Dropdown Plant -->
      <label for="plantSelect">Pilih Plant</label>
      <select id="plantSelect">
        <option value="">-- Pilih Plant --</option>
        <option value="CTA1">CTA1</option>
        <option value="CTA2">CTA2</option>
      </select>

      <!-- Dropdown Equipment -->
      <label for="equipmentSelect">Pilih Equipment</label>
      <select id="equipmentSelect" disabled>
        <option value="">-- Pilih Equipment --</option>
      </select>

      <!-- Dropdown Inspection -->
      <label for="inspectionSelect">Pilih Inspection</label>
      <select id="inspectionSelect" disabled>
        <option value="">-- Pilih Inspection --</option>
      </select>
    </div>

    <div id="criteriaContainer" class="criteria-box"></div>

    <button id="btnHitung" class="btn" style="display:none;">Hitung SAW</button>

    <div id="hasil" class="hasil-box"></div>
  </div>

  <script>
const plantSelect = document.getElementById('plantSelect');
const equipSelect = document.getElementById('equipmentSelect');
const inspectionSelect = document.getElementById('inspectionSelect');
const criteriaContainer = document.getElementById('criteriaContainer');
const hasilDiv = document.getElementById('hasil');
const btnHitung = document.getElementById('btnHitung');

const LIKERT = [0.4, 0.3, 0.2, 0.1];

// helper: fetch json
async function fetchJson(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error('Network error');
  return await res.json();
}

/* ---------- PLANT => EQUIPMENT ---------- */
plantSelect.addEventListener('change', async function() {
  const plant = this.value;
  equipSelect.disabled = true;
  equipSelect.innerHTML = '<option value="">-- Pilih Equipment --</option>';
  inspectionSelect.innerHTML = '<option value="">-- Pilih Inspection --</option>';
  inspectionSelect.disabled = true;
  criteriaContainer.innerHTML = '';
  hasilDiv.innerHTML = '';
  btnHitung.style.display = 'none';

  if (!plant) return;

  try {
    const data = await fetchJson(`get_data.php?plant=${encodeURIComponent(plant)}`);
    equipSelect.disabled = false;
    data.forEach(row => {
      const opt = document.createElement('option');
      opt.value = row.id;
      opt.text = row.id_equipment_name;
      equipSelect.appendChild(opt);
    });
  } catch (e) {
    alert('Gagal ambil equipment: ' + e.message);
  }
});

/* ---------- EQUIPMENT => INSPECTION ---------- */
equipSelect.addEventListener('change', async function() {
  const equipmentId = this.value;
  inspectionSelect.disabled = true;
  inspectionSelect.innerHTML = '<option value="">-- Pilih Inspection --</option>';
  criteriaContainer.innerHTML = '';
  hasilDiv.innerHTML = '';
  btnHitung.style.display = 'none';

  if (!equipmentId) return;

  try {
    const data = await fetchJson(`get_data.php?equipment_id=${encodeURIComponent(equipmentId)}`);
    inspectionSelect.disabled = false;
    data.forEach(row => {
      const opt = document.createElement('option');
      opt.value = row.id;
      opt.text = row.inspection_name;
      inspectionSelect.appendChild(opt);
    });
  } catch (e) {
    alert('Gagal ambil inspection: ' + e.message);
  }
});

/* ---------- INSPECTION => TAMPIL KRITERIA ---------- */
inspectionSelect.addEventListener('change', async function() {
  const inspectionId = this.value;
  criteriaContainer.innerHTML = '';
  hasilDiv.innerHTML = '';
  btnHitung.style.display = 'none';

  if (!inspectionId) return;

  try {
    const criteria = await fetchJson('get_data.php?type=criteria');

    // build UI
    criteriaContainer.innerHTML = '<h3>Masukkan Nilai & Bobot Tiap Kriteria</h3>';
    criteria.forEach(c => {
      const wrapper = document.createElement('div');
      wrapper.className = 'criteria-item';
      wrapper.innerHTML = `
        <label>${escapeHtml(c.name)} <span>(${escapeHtml(c.kriteria)})</span></label>
        <div class="inputs">
          <input type="number" step="0.01" min="0" max="10" placeholder="Nilai (0–10)" id="nilai_${c.id}">
          <select id="bobot_${c.id}" class="bobotSelect">
            <option value="">Bobot</option>
            ${LIKERT.map(v => `<option value="${v}">${v}</option>`).join('')}
          </select>
        </div>
      `;
      criteriaContainer.appendChild(wrapper);
    });

    // setup bobot uniqueness handling
    setupBobotHandlers();
    btnHitung.style.display = 'block';
  } catch (e) {
    alert('Gagal ambil kriteria: ' + e.message);
  }
});

/* ---------- HANDLE BOBOT (prevent duplicate) ---------- */
function setupBobotHandlers() {
  const selects = Array.from(document.querySelectorAll('.bobotSelect'));
  // clear any previous dataset
  selects.forEach(s => { s.dataset.prev = ''; });

  function refreshOptions() {
    const used = selects.map(s => s.value).filter(v => v);
    selects.forEach(s => {
      const current = s.value;
      Array.from(s.options).forEach(opt => {
        if (!opt.value) return; // keep placeholder
        // disable if used by other select
        opt.disabled = used.includes(opt.value) && opt.value !== current;
      });
    });
  }

  selects.forEach(s => {
    s.addEventListener('change', () => {
      // when changed, refresh all options to disable used ones
      refreshOptions();
    });
  });

  // initial refresh
  refreshOptions();
}

/* ---------- HITUNG SAW (perbaikan) ---------- */
btnHitung.addEventListener('click', async function() {
  const criteriaElems = Array.from(document.querySelectorAll('.criteria-item'));
  if (criteriaElems.length === 0) {
    alert('Pilih inspection terlebih dahulu.');
    return;
  }

  // gather inputs
  const rows = [];
  for (const el of criteriaElems) {
    const idMatch = el.querySelector('input[id^="nilai_"]').id.match(/nilai_(\d+)/);
    const id = idMatch ? idMatch[1] : null;
    if (!id) continue;
    const nilaiEl = document.getElementById(`nilai_${id}`);
    const bobotEl = document.getElementById(`bobot_${id}`);

    const nilai = parseFloat(nilaiEl.value);
    const bobot = parseFloat(bobotEl.value);

    if (isNaN(nilai) || nilai < 0 || nilai > 10) {
      alert('Isi semua nilai kriteria dengan angka antara 0 sampai 10.');
      nilaiEl.focus();
      return;
    }
    if (isNaN(bobot)) {
      alert('Pastikan semua bobot telah dipilih (unik untuk tiap kriteria).');
      bobotEl.focus();
      return;
    }
    rows.push({ id: parseInt(id), name: null, nilai, bobot, kriteriaType: null });
  }

  // validate bobot uniqueness and sum = 1
  const bobotVals = rows.map(r => r.bobot);
  const unique = Array.from(new Set(bobotVals));
  if (unique.length !== bobotVals.length) {
    alert('Setiap bobot harus unik (tiap bobot Likert hanya boleh dipakai sekali).');
    return;
  }
  const sumBobot = bobotVals.reduce((a,b)=>a+b,0);
  // allow tiny float error
  if (Math.abs(sumBobot - 1.0) > 0.0001) {
    alert(`Jumlah bobot harus 1. Saat ini: ${sumBobot.toFixed(3)}. Gunakan kombinasi 0.4+0.3+0.2+0.1.`);
    return;
  }

  // For correct benefit/cost handling we need criteria types from server
  // get criteria metadata
  let criteriaMeta = [];
  try {
    criteriaMeta = await fetchJson('get_data.php?type=criteria');
  } catch (e) {
    alert('Gagal ambil metadata kriteria: ' + e.message);
    return;
  }

  // attach name and kriteria type to rows by id
  for (const r of rows) {
    const meta = criteriaMeta.find(m => parseInt(m.id) === r.id);
    if (meta) {
      r.name = meta.name;
      r.kriteriaType = meta.kriteria; // 'benefit' or 'cost'
    } else {
      r.name = 'Kriteria ' + r.id;
      r.kriteriaType = 'benefit';
    }
  }

  // normalize nilai: we divide by 10 (since input 0-10) -> 0..1
  rows.forEach(r => {
    r.normalized = (r.nilai / 10);
    // for cost type, we convert by (1 - normalized) so lower original -> higher preference
    if (r.kriteriaType === 'cost') {
      r.normalized = 1 - r.normalized;
    }
  });

  // compute SAW
  let total = 0;
  rows.forEach(r => {
    total += r.normalized * r.bobot;
  });

  // build result HTML
  let html = `<h3>📊 Hasil Perhitungan SAW</h3>
    <table>
      <tr><th>Kriteria</th><th>Nilai (orig)</th><th>Normalisasi</th><th>Bobot</th><th>Nilai Akhir</th></tr>`;
  rows.forEach(r => {
    const nilaiAkhir = r.normalized * r.bobot;
    html += `<tr>
      <td>${escapeHtml(r.name)}</td>
      <td>${r.nilai}</td>
      <td>${r.normalized.toFixed(3)}</td>
      <td>${r.bobot}</td>
      <td>${nilaiAkhir.toFixed(3)}</td>
    </tr>`;
  });
  html += `</table><div class="total">Total Nilai SAW: <strong>${total.toFixed(3)}</strong></div>`;

  hasilDiv.innerHTML = html;
});

/* ---------- small helpers ---------- */
function escapeHtml(text) {
  if (!text) return '';
  return text.replace(/[&<>"'`=\/]/g, function (s) {
    return ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'
    })[s];
  });
}
</script>

</body>
</html>
