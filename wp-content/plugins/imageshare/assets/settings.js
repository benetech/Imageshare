window.addEventListener('DOMContentLoaded', function () {
    console.debug('[Imageshare] settings loaded');

    const ensureGroupsBtn = document.getElementById('imageshare-ensure-groups');
    const ensureGroupsStatus = document.getElementById('imageshare-ensure-groups-status');

    const migrateDefaultStateAndParentBtn = document.getElementById('imageshare-migrate-default-state-and-parent-resource-handling');
    const migrateDefaultStateAndParentStatus = document.getElementById('imageshare-migrate-default-state-and-parent-resource-handling-status')

    const migrateIntroduceJoinTablesBtn = document.getElementById('imageshare-migrate-introduce-join-tables');
    const migrateIntroduceJoinTablesStatus = document.getElementById('imageshare-migrate-introduce-join-tables-status')

    const verifyResourceGroups = () => {
        let offset = 0;
        let fixed = 0;
        let errors = 0;
        let size = 0;

        ensureGroupsBtn.setAttribute('disabled', '');

        const doRequest = () => {
            const formData = new FormData();

            formData.append('_ajax_nonce', imageshare_ajax_obj.nonce);
            formData.append('action', 'imageshare_verify_default_resource_file_group');
            formData.append('offset', offset);
            formData.append('fixed', fixed);
            formData.append('errors', errors);

            if (size) {
                formData.append('size', size);
            }

            fetch(imageshare_ajax_obj.ajax_url, {
                mode: 'cors',
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.json();
                }

                size = 5;

                ensureGroupsStatus.textContent = ('Unexpected response code. Decreasing batch size to 5.');

                return null;
            })
            .then(response => {
                if (response === null) {
                    return doRequest();
                }

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

    const migrateDefaultStateAndParent = () => {
        let offset = 0;
        let fixed = 0;
        let errors = 0;
        let size = 0;

        const button = migrateDefaultStateAndParentBtn;
        const status = migrateDefaultStateAndParentStatus;

        button.setAttribute('disabled', '');

        const doRequest = () => {
            const formData = new FormData();

            formData.append('_ajax_nonce', imageshare_ajax_obj.nonce);
            formData.append('action', 'imageshare_migrate_file_groups_settings');
            formData.append('offset', offset);
            formData.append('fixed', fixed);
            formData.append('errors', errors);

            if (size) {
                formData.append('size', size);
            }

            fetch(imageshare_ajax_obj.ajax_url, {
                mode: 'cors',
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.json();
                }

                size = 5;

                status.textContent = ('Unexpected response code. Decreasing batch size to 5.');

                return null;
            })
            .then(response => {
                if (response === null) {
                    return doRequest();
                }

                offset = response.offset;
                fixed = response.fixed;
                errors = response.errors;

                total = response.offset + response.size;

                if (response.size == 0) {
                    status.textContent = `Finished processing. Processed: ${total}, fixed: ${fixed}, errors: ${errors}.`;
                    button.removeAttribute('disabled');
                } else {
                    status.textContent = `Running. Processed: ${total}, fixed: ${fixed}.`;
                    doRequest();
                }
            })
            .catch(error => {
                console.error(error);
                status.textContent = `Processing error: ${error.message}`;
                button.removeAttribute('disabled');
            });
        };

        doRequest();
    };

    const migrateIntroduceJoinTables = () => {
        let offset = 0;
        let fixed = 0;
        let errors = 0;
        let size = 10;

        const button = migrateIntroduceJoinTablesBtn;
        const status = migrateIntroduceJoinTablesStatus;

        button.setAttribute('disabled', '');

        const doRequest = () => {
            const formData = new FormData();

            formData.append('_ajax_nonce', imageshare_ajax_obj.nonce);
            formData.append('action', 'imageshare_migrate_introduce_join_tables');
            formData.append('offset', offset);
            formData.append('fixed', fixed);
            formData.append('errors', errors);

            if (size) {
                formData.append('size', size);
            }

            fetch(imageshare_ajax_obj.ajax_url, {
                mode: 'cors',
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.json();
                }

                size = 5;

                status.textContent = ('Unexpected response code. Decreasing batch size to 5.');

                return null;
            })
            .then(response => {
                if (response === null) {
                    return doRequest();
                }

                offset = response.offset;
                fixed = response.fixed;
                errors = response.errors;

                total = response.offset + response.size;

                if (response.size == 0) {
                    status.textContent = `Finished processing. Processed: ${total}, fixed: ${fixed}, errors: ${errors}.`;
                    button.removeAttribute('disabled');
                } else {
                    status.textContent = `Running. Processed: ${total}, fixed: ${fixed}.`;
                    doRequest();
                }
            })
            .catch(error => {
                console.error(error);
                status.textContent = `Processing error: ${error.message}`;
                button.removeAttribute('disabled');
            });
        };

        doRequest();
    };


    ensureGroupsBtn && ensureGroupsBtn.addEventListener('click', verifyResourceGroups);
    migrateDefaultStateAndParentBtn && migrateDefaultStateAndParentBtn.addEventListener('click', migrateDefaultStateAndParent);
    migrateIntroduceJoinTablesBtn && migrateIntroduceJoinTablesBtn.addEventListener('click', migrateIntroduceJoinTables);
});
