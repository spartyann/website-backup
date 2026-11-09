const api = {
    setApiPWD: (pwd) => { api.apipwd = pwd; },

    call: (operation, params, callBack = null, errorCallback = null) => {
        const data = { ...params, apipwd: params.apipwd ?? api.apipwd, operation };
        const fail = result => {
            alert(result.message);
            if (errorCallback) errorCallback(result);
        };
        return $.post('api.php', data, result => {
            if (result.success === false) fail(result);
            else if (callBack) callBack(result);
        }, 'json').fail(xhr => {
            fail({ message: xhr.responseJSON?.message || 'Erreur de communication avec le serveur' });
        });
    },

    download: (operation, params) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api.php';
        form.target = '_blank';
        form.rel = 'noopener';
        for (const [name, value] of Object.entries({ ...params, operation, apipwd: api.apipwd })) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }
};
window.api = api;
