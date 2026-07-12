<template>
	<div class="container">
		<div class="row">
			<div class="col-12">
				<h1>Liste des tâches</h1>

				<div class="list-group">
					<template v-for="(group) in taskGroups">
						<task_group :group="group"></task_group>
					</template>
				</div>

			</div>
		</div>
	</div>

</template>

<script>

const { loadModule } = window['vue3-sfc-loader'];

export default {

	components: {
		'task_group': Vue.defineAsyncComponent( () => loadModule('./assets/js/task_group.vue?v=' + VERSION, vueLoaderOptions) ),
	},

	props: [ ],

	data() {

		return {
			taskGroups: null

		}
	},

	mounted(){
		let self = this;

		window.api.call("load", { }, (result) => {
			self.taskGroups = result.taskGroups;

		});
	},

	methods: {


	}

}

</script>
