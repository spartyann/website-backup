<template>
	<div class="container">
		<div class="row">
			<div class="col-12">
				<h1>Veuillez saisir le mot de passe</h1>
				
				<p class="mt-5">
					<input type="password" v-model="apipwd" class="form-control" placeholder="Mot de passe" @keyup.enter="validate">
				</p>
				<p>
					<button ref="btn" class="btn btn-primary" @click="validate">Valider</button>
				</p>
			</div>
		</div>
	</div>

</template>	

<script>

export default {
	
	data() {

		return {
			apipwd : "admin",

		}
	},
	mounted(){
		this.$refs.btn.focus();
		
	},

	methods: {
		
		validate()
		{
			let apipwd = this.apipwd;

			window.api.call("login", { apipwd: apipwd }, (result) => {
				window.api.setApiPWD(apipwd);
				this.$emit("ok", apipwd);
			});
		}
	}
	
}

</script>
