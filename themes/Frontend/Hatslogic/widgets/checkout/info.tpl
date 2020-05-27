

                {* Maincategories navigation top *}
                {block name='frontend_index_navigation_categories_top'}
                    <nav class="navigation-main">
                        <div class="container" data-menu-scroller="true" data-listSelector=".navigation--list.container" data-viewPortSelector=".navigation--list-wrapper">
                            {block name="frontend_index_navigation_categories_top_include"}
                                {include file='frontend/index/main-navigation.tpl'}
                            {/block}
                        </div>
                    </nav>
                {/block}
{* Cart entry *}
{block name="frontend_index_checkout_actions_cart"}
    <li class="navigation--entry entry--cart" role="menuitem">
        {s namespace="frontend/index/checkout_actions" name="IndexLinkCart" assign="snippetIndexLinkCart"}{/s}
        <a class="btn is--icon-left cart--link" href="{url controller='checkout' action='cart'}" title="{$snippetIndexLinkCart|escape}" aria-label="{$snippetIndexLinkCart|escape}">
            <span class="cart--display">
                {if $sUserLoggedIn}
                    {s name='IndexLinkCheckout' namespace='frontend/index/checkout_actions'}{/s}
                {else}
                    {s namespace='frontend/index/checkout_actions' name='IndexLinkCart'}{/s}
                {/if}
            </span>

            <span class="badge is--primary is--minimal cart--quantity{if $sBasketQuantity < 1} is--hidden{/if}">{$sBasketQuantity}</span>

            <i class="icon--basket"></i>

            <span class="cart--amount">
                {$sBasketAmount|currency} {s name="Star" namespace="frontend/listing/box_article"}{/s}
            </span>
        </a>
        <div class="ajax-loader">&nbsp;</div>
    </li>
{/block}

{block name="frontend_index_checkout_actions_inner"}{/block}
