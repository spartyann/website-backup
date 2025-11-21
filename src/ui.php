<?php

require_once(__DIR__ . '/vendor/autoload.php');

?><!DOCTYPE html>
<html lang="fr">
	<head>
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta charset="UTF-8">
		<meta name="viewport" content="user-scalable=yes, width=device-width, initial-scale=1, maximum-scale=5">
		<meta name="theme-color" content="#1976d2">
		<meta name="description" content="Quantic Music">
		<title>Backup</title>
		<script src="assets/jquery/jquery-3.7.1.min.js" ></script>
		<link href="assets/font-awesome-4.7.0/css/font-awesome.min.css" rel="stylesheet">
		<script src="assets/vue/vue3-sfc-loader.js"></script>
		<script src="assets/vue/vue.global.js"></script>
		<link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
		<script src="assets/bootstrap/js/bootstrap.bundle.min.js" ></script>
		<script src="assets/js/app.js" ></script>

		<script>
			const VERSION = 1;

			const vueLoaderOptions = {
				moduleCache: {
					vue: Vue
				},
				async getFile(url) {
					
					const res = await fetch(url);
					if ( !res.ok ) throw Object.assign(new Error(res.statusText + ' ' + url), { res });
					return { getContentData: asBinary => asBinary ? res.arrayBuffer() : res.text(), }
				},
				addStyle(textContent) {

					const style = Object.assign(document.createElement('style'), { textContent });
					const ref = document.head.getElementsByTagName('style')[0] || null;
					document.head.insertBefore(style, ref);
				},
			}

			const { loadModule } = window['vue3-sfc-loader'];


			$(() => {
				const app = Vue.createApp({
					components: {
						'app': Vue.defineAsyncComponent( () => loadModule('./assets/js/app.vue?v=' + VERSION, vueLoaderOptions) )
					},
				});

				app.mount('#app');
			});
		</script>

		<link href="assets/css/app.css?v=VERSION" rel="stylesheet">
	</head>

	<body class="p-4" id="app">

		<app></app>

	</body>
</html>

