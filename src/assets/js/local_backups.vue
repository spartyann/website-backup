<template>
    <div class="container">
        <h1>Archives locales</h1>
        <div class="d-flex gap-2 my-3">
            <input v-model="search" type="search" class="form-control" placeholder="Rechercher une archive" aria-label="Rechercher une archive">
            <button class="btn btn-outline-primary" :disabled="loading" @click="refresh">Actualiser</button>
        </div>
        <p v-if="loading" role="status">Chargement…</p>
        <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
        <p v-if="!loading && !error && !backups.length">Aucune archive locale disponible.</p>
        <p v-else-if="!loading && !error && !filtered.length">Aucune archive ne correspond à la recherche.</p>
        <div v-if="filtered.length" class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Fichier</th><th>Date de sauvegarde</th><th>Taille</th><th>Téléchargement</th></tr></thead>
                <tbody>
                    <tr v-for="backup in filtered" :key="backup.id">
                        <td class="text-break">{{ backup.name }}</td>
                        <td>{{ new Date(backup.created_at).toLocaleString('fr-FR') }}</td>
                        <td>{{ readableSize(backup.size) }}</td>
                        <td><button class="btn btn-sm btn-primary" @click="download(backup)">Télécharger</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
export default {
    data() { return { backups: [], search: '', loading: false, error: '' }; },
    computed: {
        filtered() { return this.backups.filter(backup => backup.name.toLowerCase().includes(this.search.toLowerCase())); }
    },
    mounted() { this.refresh(); },
    methods: {
        refresh() {
            this.loading = true;
            this.error = '';
            window.api.call('list_local_backups', {}, result => {
                this.backups = result.backups;
                this.loading = false;
            }, result => {
                this.error = result.message;
                this.loading = false;
            });
        },
        download(backup) {
            // Un formulaire laisse le navigateur écrire sur disque, sans Blob en mémoire.
            window.api.download('download_local_backup', { id: backup.id });
        },
        readableSize(bytes) {
            const units = ['o', 'Ko', 'Mo', 'Go', 'To'];
            let index = 0;
            while (bytes >= 1024 && index < units.length - 1) { bytes /= 1024; index++; }
            return bytes.toLocaleString('fr-FR', { maximumFractionDigits: 2 }) + ' ' + units[index];
        }
    }
};
</script>
