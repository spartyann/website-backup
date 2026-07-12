<template>

	<div>
		<div v-if="authenticated">
			<ul class="nav nav-tabs mb-4">
				<li class="nav-item">
					<a class="nav-link" :class="{ active: activeTab === 'backups' }" @click="activeTab = 'backups'">Backups</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" :class="{ active: activeTab === 'tasks' }" @click="activeTab = 'tasks'">Tâches</a>
				</li>
			</ul>

			<backup v-show="activeTab === 'backups'"></backup>
			<tasks v-show="activeTab === 'tasks'"></tasks>
		</div>
		<code_input v-else v-on:ok="processAuthenticated"></code_input>
	</div>

</template>

<script>

const { loadModule } = window['vue3-sfc-loader'];

export default {

	components: {
		'backup': Vue.defineAsyncComponent( () => loadModule('./assets/js/backup.vue?v=' + VERSION, vueLoaderOptions) ),
		'tasks': Vue.defineAsyncComponent( () => loadModule('./assets/js/tasks.vue?v=' + VERSION, vueLoaderOptions) ),
		'code_input': Vue.defineAsyncComponent( () => loadModule('./assets/js/code_input.vue?v=' + VERSION, vueLoaderOptions) )
	},

	data() {
		//let authenticated = localStorage.getItem('authenticated', "0")

		return {
			authenticated : false,
			activeTab: 'backups',
		}
	},


	methods:{
		processAuthenticated(apipwd)
		{
			this.authenticated = true;
		},

		logout(){
			this.authenticated = false;
		}
	}
	
}

</script>
