window.addEventListener('DOMContentLoaded', function () {
    console.debug('[Imageshare] settings loaded', imageshare_ajax_obj);

    const ensureGroupsBtn = document.getElementById('imageshare-ensure-groups');
    const ensureGroupsStatus = document.getElementById('imageshare-ensure-groups-status');

    const verifyResourceGroups = () => {
        let offset = 0;
        let fixed = 0;
        let errors = 0;

        ensureGroupsBtn.setAttribute('disabled', '');

        const doRequest = () => {
            const formData = new FormData();

            formData.append('_ajax_nonce', imageshare_ajax_obj.nonce);
            formData.append('action', 'imageshare_verify_default_resource_file_group');
            formData.append('offset', offset);
            formData.append('fixed', fixed);
            formData.append('errors', errors);

            fetch(imageshare_ajax_obj.ajax_url, {
                mode: 'cors',
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(response => {
                console.debug(response);

                offset = response.offset;
                fixed = response.fixed;
                errors = response.errors;

                total = response.offset + response.size;

                if (response.size == 0) {
                    ensureGroupsStatus.textContent = `Finished processing. Processed: ${total}, fixed: ${fixed}, errors: ${errors}.`;
                    ensureGroupsBtn.removeAttribute('disabled');
                } else {
                    ensureGroupsStatus.textContent = `Running. Processed: ${total}, fixed: ${fixed}.`;
                    doRequest();
                }
            })
            .catch(error => {
                console.error(error);
                ensureGroupsStatus.textContent = `Processing error: ${error.message}`;
                ensureGroupsBtn.removeAttribute('disabled');
            });
        };

        doRequest();
    };

    ensureGroupsBtn.addEventListener('click', verifyResourceGroups);

});
