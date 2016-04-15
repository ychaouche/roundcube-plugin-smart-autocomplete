/*
 * Register accepted autocomplete suggestion
 */
function smart_autocomplete_select (p)
{
    rcmail.http_post('plugin.smart_autocomplete.register_accepted_suggestion', {
        search_string   : p.search,
        accepted_type   : p.result_type,
        accepted_id     : p.data.id,
        accepted_email  : p.data.email,
        accepted_source : p.data.source,
    });
}



/*
 * Event listeners
 */
$(document).ready(function() {
    rcmail.addEventListener('autocomplete_insert', function(p) { smart_autocomplete_select(p); });
})
