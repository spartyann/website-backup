<template>
	<div class="mt-2">

		<p>
			<span v-if="result.result" class="badge me-2" :class="result.result === 'OK' ? 'bg-success' : 'bg-danger'">{{ result.result }}</span>
			<span v-if="items.length > 0">{{ filteredItems.length }} / {{ items.length }} élément(s)</span>
		</p>

		<div v-if="items.length > 0">

			<div class="mb-2 d-flex flex-wrap gap-2 align-items-center">
				<div class="btn-group btn-group-sm" role="group">
					<button type="button" class="btn btn-outline-secondary" :class="{ active: activeExtension === 'all' }" @click="activeExtension = 'all'">Tous</button>
					<button type="button" class="btn btn-outline-secondary" :class="{ active: activeExtension === 'core' }" v-if="hasCoreItems" @click="activeExtension = 'core'">Noyau ({{ countByExtension('core') }})</button>
					<button type="button" class="btn btn-outline-secondary" :class="{ active: activeExtension === ext.key }" v-for="ext in extensions" :key="ext.key" @click="activeExtension = ext.key">{{ ext.key }} ({{ ext.count }})</button>
				</div>

				<div class="btn-group btn-group-sm" role="group">
					<button type="button" class="btn btn-outline-secondary" :class="{ active: activeType === 'all' }" @click="activeType = 'all'">Tous types</button>
					<button type="button" class="btn btn-outline-secondary" :class="{ active: activeType === 'Fichier' }" @click="activeType = 'Fichier'">Fichiers</button>
					<button type="button" class="btn btn-outline-secondary" :class="{ active: activeType === 'Dossier' }" @click="activeType = 'Dossier'">Dossiers</button>
				</div>

				<div class="btn-group btn-group-sm flex-wrap" role="group" v-if="fileExtensions.length > 0">
					<button type="button" class="btn btn-outline-secondary" :class="{ active: activeFileExt === 'all' }" @click="activeFileExt = 'all'">Toutes extensions</button>
					<button type="button" class="btn btn-outline-secondary" :class="{ active: activeFileExt === fe.key }" v-for="fe in fileExtensions" :key="fe.key" @click="activeFileExt = fe.key">.{{ fe.key }} ({{ fe.count }})</button>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table table-sm table-striped align-middle">
					<thead>
						<tr>
							<th>Statut</th>
							<th>Type</th>
							<th>Chemin</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="item in filteredItems" :key="item.key">
							<td><span class="badge" :class="item.badgeClass">{{ item.status }}</span></td>
							<td>{{ item.type }}</td>
							<td class="text-break">{{ item.display }}</td>
							<td class="text-end">
								<span v-if="item.deleted" class="text-muted"><i class="fa fa-check" aria-hidden="true"></i> Supprimé</span>
								<button v-else-if="item.deletable" class="btn btn-sm btn-outline-danger" :disabled="item.deleting" @click="deleteItem(item)">
									<i class="fa fa-trash" aria-hidden="true"></i> Supprimer
								</button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div v-if="dbItems.length > 0" class="table-responsive">
			<table class="table table-sm table-striped align-middle">
				<thead>
					<tr>
						<th>Table</th>
						<th>Clé primaire</th>
						<th>Colonne</th>
						<th>Déclencheur</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(item, i) in dbItems" :key="i">
						<td>{{ item.table }}</td>
						<td>{{ item.pk_column ? item.pk_column + ' = ' + item.pk_value : '-' }}</td>
						<td>{{ item.column }}</td>
						<td>{{ item.trigger }}</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div v-if="watchlistHits.length > 0">
			<h5>Liste de surveillance</h5>
			<table class="table table-sm table-striped align-middle">
				<thead>
					<tr>
						<th>Extension</th>
						<th>Version installée</th>
						<th>Vulnérable en dessous de</th>
						<th>Note</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(item, i) in watchlistHits" :key="i">
						<td>{{ item.name }} ({{ item.element }})</td>
						<td>{{ item.installed_version }}</td>
						<td>{{ item.below_version }}</td>
						<td>{{ item.note }}</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div v-if="velHits.length > 0">
			<h5>Correspondances VEL (indicatif)</h5>
			<table class="table table-sm table-striped align-middle">
				<thead>
					<tr>
						<th>Extension</th>
						<th>Entrée VEL</th>
						<th>Lien</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(item, i) in velHits" :key="i">
						<td>{{ item.extension_name }} ({{ item.extension_element }})</td>
						<td>#{{ item.vel_id }} {{ item.vel_title }}</td>
						<td><a v-if="item.jed" :href="item.jed" target="_blank" rel="noopener">JED</a></td>
					</tr>
				</tbody>
			</table>
		</div>

		<pre v-if="items.length === 0 && dbItems.length === 0 && watchlistHits.length === 0 && velHits.length === 0 && (result.result_strings || []).length > 0" class="border p-3">{{ (result.result_strings || []).join("\n") }}</pre>

	</div>

</template>

<script>

export default {

	props: [ "group", "task", "result" ],

	data() {
		return {
			items: this.buildItems(),
			activeExtension: 'all',
			activeType: 'all',
			activeFileExt: 'all',
		}
	},

	computed: {
		dbItems() {
			return this.result.database_items_found || [];
		},
		watchlistHits() {
			return this.result.watchlist_hits || [];
		},
		velHits() {
			return this.result.vel_hits || [];
		},

		hasCoreItems() {
			return this.items.some(item => item.extension === 'core');
		},

		// Liste des extensions détectées (préfixe "[element]" dans les chemins), avec leur nombre d'éléments
		extensions() {
			const counts = {};
			this.items.forEach(item => {
				if (item.extension === 'core') return;
				counts[item.extension] = (counts[item.extension] || 0) + 1;
			});

			return Object.keys(counts).sort().map(key => ({ key, count: counts[key] }));
		},

		// Extensions de fichier détectées (ex: php, xml, ini) parmi les éléments de type Fichier, avec leur nombre
		fileExtensions() {
			const counts = {};
			this.items.forEach(item => {
				if (item.fileExt === null) return;
				counts[item.fileExt] = (counts[item.fileExt] || 0) + 1;
			});

			return Object.keys(counts).sort().map(key => ({ key, count: counts[key] }));
		},

		filteredItems() {
			return this.items.filter(item => {
				if (this.activeExtension !== 'all' && item.extension !== this.activeExtension) return false;
				if (this.activeType !== 'all' && item.type !== this.activeType) return false;
				if (this.activeFileExt !== 'all' && item.fileExt !== this.activeFileExt) return false;
				return true;
			});
		},
	},

	methods: {

		countByExtension(key) {
			return this.items.filter(item => item.extension === key).length;
		},

		buildItems() {
			const r = this.result;
			const list = [];

			const push = (paths, status, type, badgeClass, deletable) => {
				(paths || []).forEach(p => {
					const m = p.match(/^\[([^\]]+)\]\s*(.+)$/);
					const rawPath = m ? m[2] : p;

					let fileExt = null;
					if (type === 'Fichier') {
						const name = rawPath.split('/').pop();
						const dot = name.lastIndexOf('.');
						fileExt = (dot > 0) ? name.substring(dot + 1).toLowerCase() : '(sans extension)';
					}

					list.push({
						key: type + ':' + status + ':' + p,
						raw: p,
						display: p,
						extension: m ? m[1] : 'core',
						fileExt,
						status, type, badgeClass, deletable,
						deleting: false,
						deleted: false,
					});
				});
			};

			push(r.added_folders, 'Ajouté', 'Dossier', 'bg-warning text-dark', true);
			push(r.added_files, 'Ajouté', 'Fichier', 'bg-warning text-dark', true);
			push(r.updated_files, 'Modifié', 'Fichier', 'bg-danger', false);
			push(r.missing_folders, 'Manquant', 'Dossier', 'bg-secondary', false);
			push(r.missing_files, 'Manquant', 'Fichier', 'bg-secondary', false);

			return list;
		},

		// Un chemin d'extension est préfixé "[element] chemin/réel" : on retire ce préfixe pour l'appel API,
		// qui a besoin du chemin relatif réel par rapport à folder_root.
		rawPath(item) {
			const m = item.raw.match(/^\[[^\]]+\]\s*(.+)$/);
			return m ? m[1] : item.raw;
		},

		deleteItem(item) {
			const label = item.type === 'Dossier' ? 'ce dossier et tout son contenu' : 'ce fichier';

			if (confirm(`Voulez-vous vraiment supprimer ${label} ?\n\n${item.display}`) == false) return;

			item.deleting = true;

			window.api.call("delete_item", {
				group: this.group,
				task_name: this.task,
				path: this.rawPath(item),
				type: item.type === 'Dossier' ? 'folder' : 'file',
			}, () => {
				item.deleting = false;
				item.deleted = true;
			}, () => {
				item.deleting = false;
			});
		}

	}

}

</script>
