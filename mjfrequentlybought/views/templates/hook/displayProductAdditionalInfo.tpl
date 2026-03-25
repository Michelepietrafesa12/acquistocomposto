{**
 * MJ Frequently Bought Together - Frontend Template
 *
 * @author MJ Digital
 * @version 1.0.0
 *}

{if $mjfbt_products|count > 0}
<div class="mjfbt-container" style="background-color: {$mjfbt_bg_color|escape:'htmlall':'UTF-8'};" data-ajax-url="{$mjfbt_ajax_url|escape:'htmlall':'UTF-8'}">
    <h3 class="mjfbt-title">{l s='Spesso comprati insieme' mod='mjfrequentlybought'}</h3>
    <p class="mjfbt-subtitle">
        {l s='Questi prodotti ti sono stati' mod='mjfrequentlybought'}
        <strong>{l s='consigliati in base agli acquisti degli altri utenti' mod='mjfrequentlybought'}</strong>.
        {l s='Seleziona i prodotti che ti interessano e aggiungili al carrello' mod='mjfrequentlybought'}
    </p>

    <div class="mjfbt-products-grid">
        {foreach from=$mjfbt_products item=product}
        <div class="mjfbt-product-card"
             data-id-product="{$product.id_product|intval}"
             data-id-product-attribute="{$product.id_product_attribute|intval}"
             data-price="{$product.price_amount|escape:'htmlall':'UTF-8'}"
             data-price-display="{$product.price|escape:'htmlall':'UTF-8'}">

            <div class="mjfbt-checkbox-wrapper">
                <input type="checkbox" class="mjfbt-checkbox" aria-label="{l s='Seleziona' mod='mjfrequentlybought'} {$product.name|escape:'htmlall':'UTF-8'}">
            </div>

            {if $product.image_url}
            <a href="{$product.link|escape:'htmlall':'UTF-8'}">
                <img src="{$product.image_url|escape:'htmlall':'UTF-8'}"
                     alt="{$product.name|escape:'htmlall':'UTF-8'}"
                     class="mjfbt-product-image"
                     loading="lazy">
            </a>
            {/if}

            <a href="{$product.link|escape:'htmlall':'UTF-8'}" class="mjfbt-product-name">
                {$product.name|escape:'htmlall':'UTF-8'}
            </a>

            {if $product.show_price}
            <div class="mjfbt-price-wrapper">
                <span class="mjfbt-price-current">{$product.price|escape:'htmlall':'UTF-8'}</span>
                {if $product.has_discount && $product.price_original}
                <span class="mjfbt-price-original">{$product.price_original|escape:'htmlall':'UTF-8'}</span>
                {/if}
            </div>
            {/if}
        </div>
        {/foreach}
    </div>

    <div class="mjfbt-bottom-bar">
        <span class="mjfbt-total-text">
            {l s='Totale per i prodotti selezionati:' mod='mjfrequentlybought'}
            <span class="mjfbt-total-value">0,00&nbsp;&euro;</span>
        </span>
        <button type="button" class="mjfbt-add-to-cart" disabled>
            {l s='Aggiungi i prodotti selezionati al carrello' mod='mjfrequentlybought'}
        </button>
    </div>

    <div class="mjfbt-success-message"></div>
    <div class="mjfbt-error-message"></div>
</div>
{/if}
