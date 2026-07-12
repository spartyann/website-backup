<template>
	<div class="list-group-item ">
		<h3>Tâches : {{ group.name }}</h3>

		<ul class="list-unstyled mb-3">
			<li v-for="item in group.items" :key="item.name" class="mb-2">
				<div class="d-flex align-items-center gap-2">
					<span>{{ item.name }}</span>
					<span class="badge bg-secondary">{{ item.task }}</span>
					<span v-if="item.integrity_type" class="badge bg-info text-dark">{{ item.integrity_type }}</span>

					<button class="btn btn-sm btn-outline-primary ms-2" v-if="taskState(item.name).running == false" @click="runSingleTask(item)">
						<i class="fa fa-play" aria-hidden="true"></i> Analyser
					</button>
					<span v-else><i class="fa fa-spinner fa-pulse fa-fw"></i> Analyse en cours...</span>
				</div>

				<task_result v-if="taskState(item.name).result" :group="group.name" :task="item.name" :result="taskState(item.name).result"></task_result>
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

const { loadModule } = window['vue3-sfc-loader'];

export default {

	components: {
		'task_result': Vue.defineAsyncComponent( () => loadModule('./assets/js/task_result.vue?v=' + VERSION, vueLoaderOptions) ),
	},

	props: [ "group" ],

	data() {

		return {
			running: false,
			log: '',

			taskStates: {}, // { [taskName]: { running: bool, result: object|null } }

		}
	},

	mounted(){
		let self = this;

	},

	methods: {

		taskState(taskName)
		{
			if (this.taskStates[taskName] === undefined)
			{
				this.taskStates[taskName] = { running: false, result: null };
			}
			return this.taskStates[taskName];
		},

		runSingleTask(item)
		{
			let self = this;
			let state = this.taskState(item.name);

			state.running = true;
			state.result = null;

			window.api.call("run_single_task", { group: this.group.name, task_name: item.name }, (res) => {
				state.running = false;
				state.result = res.result;
			}, () => {
				state.running = false;
			});
		},

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
