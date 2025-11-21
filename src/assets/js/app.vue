<template>

	<div>
		<div v-if="authenticated">
			<backup></backup>
		</div>
		<code_input v-else v-on:ok="processAuthenticated"></code_input>
	</div>

</template>

<script>

const { loadModule } = window['vue3-sfc-loader'];

export default {
	
	components: {
		'backup': Vue.defineAsyncComponent( () => loadModule('./assets/js/backup.vue?v=' + VERSION, vueLoaderOptions) ),
		'code_input': Vue.defineAsyncComponent( () => loadModule('./assets/js/code_input.vue?v=' + VERSION, vueLoaderOptions) )
	},

	data() {
		//let authenticated = localStorage.getItem('authenticated', "0")

		return {
			authenticated : false,
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
