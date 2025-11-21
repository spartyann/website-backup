


let api = {

	setApiPWD: (pwd) => {
		api.apipwd = pwd;
	},

	call: (operation, params, callBack = null, errorCallback = null) => {

		if (params.apipwd === undefined) params.apipwd = api.apipwd;
		if (params.operation === undefined) params.operation = operation;

		let jqxhr = $.post( "api.php", params, function(data) {

			if (data.success === false){
				alert(data.message);
				if (errorCallback != null){
					errorCallback(data);
					return;
				}
			} else {
				if (callBack != null) callBack(data);
			}
		})
		.done(function() {
			
		})
		.fail(function() {
			alert( "error" );
		})
		.always(function() {
			
		});
	}

}


window.api = api;
