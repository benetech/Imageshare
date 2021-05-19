console.debug('[Imageshare] Edit File Group');

window.addEventListener('load', () => {
    const field__isDefault = acf.getField(imageshare_acf_fields['is_default']);
    const field__parentResource = acf.getField(imageshare_acf_fields['parent_resource']);
    const field__files = acf.getField(imageshare_acf_fields['files']);

    acf.addFilter('relationship_ajax_data', (data, element) => {
        if(element === field__files && data['s'] && data['s'].length) {
            return data;
        }

        if (element === field__files) {
            const isDefault = !!field__isDefault.val();

            if (!isDefault) {
                // only filter parent resource default group files when we're not
                // setting a 
                const parent = field__parentResource.val();
                // we cannot send arbitrary parameters. These get filtered out.
                // abuse the search parameter to send the parent resource along.

                data['s'] = 'parent_resource_id:' + parent;
            }

            return data;
        }
    });

    field__parentResource.on('change', function () {
        // when the parent is changed, fetch the files again.
        field__files.fetch();
    });

    field__isDefault.on('change', function () {
        // when default state is toggled, fetch the files again
        field__files.fetch();
    });
});
