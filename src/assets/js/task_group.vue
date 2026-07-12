<template>
	<div class="list-group-item ">
		<h3>Tâches : {{ group.name }}</h3>

		<ul class="mb-3">
			<li v-for="item in group.items" :key="item.name">
				{{ item.name }}
				<span class="badge bg-secondary">{{ item.task }}</span>
				<span v-if="item.integrity_type" class="badge bg-info text-dark">{{ item.integrity_type }}</span>
			</li>
		</ul>

		<p>
			<button class="btn btn-primary" @click="run" v-if="running == false"><i class="fa fa-shield" aria-hidden="true"></i> Lancer le groupe</button>
			<span v-else> <i class="fa fa-spinner fa-pulse fa-fw"></i> Exécution en cours... </span>
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
			running: false,
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

			if (confirm("Voulez-vous vraiment lancer les tâches du groupe " + this.group.name + " ?"))
			{
				self.running = true;

				window.api.call("run_tasks", { group: this.group.name }, (result) => {
					self.running = false;
					self.log = result.log;

				}, () => {
					self.running = false;
				});
			}


		}
	}

}

</script>
