<template>
	<div class="list-group-item ">
		<h3>Backup: {{ group.name }}</h3>

		<p>
			<button class="btn btn-primary" @click="run" v-if="backuping == false"><i class="fa fa-database" aria-hidden="true"></i> Lancer une sauvegarde</button>
			<span v-else> <i class="fa fa-spinner fa-pulse fa-fw"></i> Sauvegarde en cours... </span>
		</p>
		<pre v-if="log != ''" class="border p-3">
{{ log }}
		</pre>
	</div>

</template>	

<script>

export default {

	props: [ "group" ],
	
	data() {

		return {
			groups: null,

			backuping: false,
			log: '',

		}
	},

	mounted(){
		let self = this;

	},

	methods: {
		
		run()
		{
			let self = this;
			self.log = '';

			if (confirm("Voulez-vous vraiment lancer une sauvegarde pour le groupe " + this.group.name + " ?"))
			{
				self.backuping = true;

				window.api.call("run_backup", { group: this.group.name }, (result) => {
					self.backuping = false;
					self.log = result.log;
					
				}, () => {
					self.backuping = false;
				});
			}

			
		}
	}
	
}

</script>
