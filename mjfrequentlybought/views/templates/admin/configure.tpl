{**
 * MJ Frequently Bought Together - Admin Configuration Template
 *
 * @author MJ Digital
 * @version 1.0.0
 *}

<div class="panel">
    <h3><i class="icon-cogs"></i> {l s='Ricostruzione Associazioni' mod='mjfrequentlybought'}</h3>
    <p>{l s='Clicca il pulsante per riscansionare tutti gli ordini e ricostruire le associazioni prodotto.' mod='mjfrequentlybought'}</p>
    <p>
        <strong>{l s='URL Cron:' mod='mjfrequentlybought'}</strong>
        <code>{$mjfbt_cron_url|escape:'htmlall':'UTF-8'}</code>
    </p>
    <button type="button" class="btn btn-primary" id="mjfbt-rebuild-btn">
        <i class="icon-refresh"></i> {l s='Ricostruisci Associazioni' mod='mjfrequentlybought'}
    </button>
    <span id="mjfbt-rebuild-status" style="margin-left: 10px;"></span>
</div>

<script type="text/javascript">
    document.getElementById('mjfbt-rebuild-btn').addEventListener('click', function() {
        var btn = this;
        var status = document.getElementById('mjfbt-rebuild-status');

        btn.disabled = true;
        btn.innerHTML = '<i class="icon-refresh icon-spin"></i> {l s='Ricostruzione in corso...' mod='mjfrequentlybought'}';
        status.textContent = '';

        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'rebuildAssociations');
        formData.append('token', '{$mjfbt_admin_token|escape:'javascript':'UTF-8'}');

        fetch('{$mjfbt_admin_ajax_url|escape:'javascript':'UTF-8'}', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="icon-refresh"></i> {l s='Ricostruisci Associazioni' mod='mjfrequentlybought'}';
            if (data.success) {
                status.innerHTML = '<span class="text-success"><i class="icon-check"></i> ' + data.message + '</span>';
            } else {
                status.innerHTML = '<span class="text-danger"><i class="icon-warning"></i> ' + data.message + '</span>';
            }
        })
        .catch(function(error) {
            btn.disabled = false;
            btn.innerHTML = '<i class="icon-refresh"></i> {l s='Ricostruisci Associazioni' mod='mjfrequentlybought'}';
            status.innerHTML = '<span class="text-danger"><i class="icon-warning"></i> Errore di rete</span>';
        });
    });
</script>
