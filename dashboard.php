<?php
    declare(strict_types=1);

    require __DIR__ . '/connect_db.php';
    require __DIR__ . '/auth.php';
	require __DIR__ . '/lang.php';
    require_login();
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>KLCD - Dashboard</title>

  <!-- ECharts -->
  <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>

	<style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0}
    .wrap{padding:18px}
		.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}
    .card{border:1px solid #ddd;border-radius:12px;padding:14px}
		.cardHeader{display:flex;align-items:center;justify-content:space-between;gap:10px}
		.actions{display:flex;gap:8px;flex-wrap:wrap}
		.btn{border:1px solid #ccc;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer;font-size:12px}
		.btn:hover{background:#f7f7f7}
    .muted{color:#666}
    .title{margin:0 0 10px 0;font-size:18px}

		@media print {
			nav, .no-print { display:none !important; }
			.card{break-inside:avoid}
			body{background:#fff}
		}
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/nav.php'; ?>

  <div class="wrap">
    <h2>Dashboard</h2>
    <p class="muted">Version maquette : graphiques uniquement (pas d’affichage de données sensibles).</p>

	<div class="toolbar">
      <label for="klcdSelect"><b>Paysage (KLCD)</b> :</label>
      <select id="klcdSelect" class="no-print">
        <option value="">Tous les paysages</option>
      </select>
      <span class="muted" id="klcdMeta"></span>
    </div>

    <div class="no-print" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:12px 0 18px 0;">
      <span id="klcdStatus" class="muted"></span>
    </div>

		<div class="grid">
		  <div class="card">
			<div class="cardHeader">
			  <h3 class="title">Répartition par Pillar</h3>
			  <div class="actions no-print">
				<button class="btn" onclick="downloadChart('pie_pillar','actions_by_pillar.png')">PNG</button>
			  </div>
			</div>
			<div id="pie_pillar" style="height:490px"></div>
			<small class="muted">Si ce camembert est vide : importer/relier <code>Action_Activity</code>.</small>
		  </div>

		  <div class="card">
			<div class="cardHeader">
			  <h3 class="title">Répartition par Programme</h3>
			  <div class="actions no-print">
				<button class="btn" onclick="downloadChart('pie_programme','actions_by_programme.png')">PNG</button>
			  </div>
			</div>
			<div id="pie_programme" style="height:490px"></div>
		  </div>

		  <div class="card">
			<div class="cardHeader">
			  <h3 class="title">Répartition par Implementer / Institution</h3>
			  <div class="actions no-print">
				<button class="btn" onclick="downloadChart('pie_donor','actions_by_donor.png')">PNG</button>
			  </div>
			</div>
			<div id="pie_donor" style="height:490px"></div>
			<small class="muted">Source : <code>Action_Implementer</code> + <code>Institution</code> (rôle « donor » ou type « donor »).</small>
		  </div>

		  <div class="card">
			<div class="cardHeader">
			  <h3 class="title">Répartition des flags</h3>
			  <div class="actions no-print">
				<button class="btn" onclick="downloadChart('pie_flags','flags_distribution.png')">PNG</button>
				<button class="btn" onclick="window.print()">PDF</button>
			  </div>
			</div>
			<div id="pie_flags" style="height:490px"></div>
		  </div>
		</div>
  </div>

  <script>
		const charts = {};

		function renderPie(containerId, title, items) {
      const el = document.getElementById(containerId);
		  // Dispose if hot-reload / repeated rendering
		  if (charts[containerId]) {
			charts[containerId].dispose();
		  }
		  const chart = echarts.init(el);
		  charts[containerId] = chart;

      const safeItems = (items || []).map(x => ({
        name: String(x.name ?? ''),
        value: Number(x.value ?? 0)
      })).filter(x => x.name.length);

      const option = {
        title: { text: title, left: 'center', top: 6, textStyle: { fontSize: 14 } },
        tooltip: { trigger: 'item' },
        legend: { bottom: 0 },
        series: [{
          type: 'pie',
          radius: ['35%','65%'],
          avoidLabelOverlap: true,
          label: { show: true, formatter: '{b}: {c}' },
          data: safeItems.length ? safeItems : [{name:'No data yet', value: 1}]
        }]
      };

      chart.setOption(option);
      window.addEventListener('resize', () => chart.resize());
    }

		function downloadChart(containerId, filename) {
		  const chart = charts[containerId];
		  if (!chart) return;
		  const url = chart.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: '#fff' });
		  const a = document.createElement('a');
		  a.href = url;
		  a.download = filename;
		  a.click();
		}

		async function loadAllAndRender(klcdId = "") {
		  try {
			const qs = klcdId ? `?klcd_id=${encodeURIComponent(klcdId)}` : '';
			const res = await fetch(`/api/stats.php${qs}`, { headers: { 'Accept': 'application/json' } });
			const data = await res.json();
			if (!res.ok || data.error) {
			  console.error('stats error', data);
			  renderPie('pie_pillar', 'Actions by Pillar', [{name: 'Error', value: 1}]);
			  renderPie('pie_programme', 'Actions by Programme', [{name: 'Error', value: 1}]);
			  renderPie('pie_donor', 'Actions by Implementer', [{name: 'Error', value: 1}]);
			  renderPie('pie_flags', 'Flags distribution', [{name: 'Error', value: 1}]);
			  return;
			}

			renderPie('pie_pillar', 'Actions by Pillar', data.pillar ?? []);
			renderPie('pie_programme', 'Actions by Programme', data.programme ?? []);
			renderPie('pie_donor', 'Actions by Implementer', data.donor ?? []);
			renderPie('pie_flags', 'Flags distribution', data.flags ?? []);
		  } catch (e) {
			console.error(e);
			renderPie('pie_pillar', 'Actions by Pillar', [{name: 'Error', value: 1}]);
			renderPie('pie_programme', 'Actions by Programme', [{name: 'Error', value: 1}]);
			renderPie('pie_donor', 'Actions by Implementer', [{name: 'Error', value: 1}]);
			renderPie('pie_flags', 'Flags distribution', [{name: 'Error', value: 1}]);
		  }
		}


		async function loadKlcdList() {
		  const sel = document.getElementById('klcdSelect');
		  const status = document.getElementById('klcdStatus');
		  if (!sel) return;
		  try {
		    status.textContent = "Chargement des paysages…";
		    const res = await fetch(`/api/stats.php?by=klcd_list`, { headers: { 'Accept': 'application/json' } });
		    const json = await res.json();
		    if (!res.ok || json.error) {
		      console.error('klcd_list error', json);
		      status.textContent = "Impossible de charger la liste des paysages.";
		      return;
		    }
		    const items = json.data ?? [];
		    // Clear options (keep first 'all')
		    sel.querySelectorAll('option:not(:first-child)').forEach(o => o.remove());
		    for (const it of items) {
		      const opt = document.createElement('option');
		      opt.value = String(it.id);
		      opt.textContent = it.name;
		      sel.appendChild(opt);
		    }
		    status.textContent = items.length ? `${items.length} paysages` : "Aucun paysage";
		  } catch (e) {
		    console.error(e);
		    status.textContent = "Erreur lors du chargement des paysages.";
		  }
		}

		function currentKlcdId() {
		  const sel = document.getElementById('klcdSelect');
		  return sel ? sel.value : "";
		}

		document.getElementById('klcdSelect')?.addEventListener('change', () => {
		  loadAllAndRender(currentKlcdId());
		});


		(async () => {
		  await loadKlcdList();
		  loadAllAndRender(currentKlcdId());
		})();
  </script>
</body>
</html>