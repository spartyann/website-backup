<template>
	<div class="container">
		<div class="row">
			<div class="col-12">
				<h1>Liste des backups</h1>
				
				<div class="list-group">
					<template v-for="(group) in groups">
						<backup_group :group="group"></backup_group>
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
		'backup_group': Vue.defineAsyncComponent( () => loadModule('./assets/js/backup_group.vue?v=' + VERSION, vueLoaderOptions) ),
	},

	props: [ ],
	
	data() {

		return {
			groups: null

		}
	},

	mounted(){
		let self = this;

		window.api.call("load", { }, (result) => {
			self.groups = result.groups;

		});
	},

	methods: {
		
		
	}
	
}

</script>
