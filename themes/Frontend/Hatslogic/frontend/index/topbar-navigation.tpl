{extends file="parent:frontend/index/topbar-navigation.tpl"}

{block name="frontend_index_checkout_actions_service_menu"}
    {* {$smarty.block.parent} *}

    <div class="ph"> 
        <a href="">Call Us Now: 0123-444-666</a>
    </div>
    <div class="lan mx-2"> 
        <select>
            <option>
                English
            </option>
            <option>
                German
            </option>
        </select>
    </div>
    <div class="Curr mx-2"> 
        <select>
            <option>
                USD
            </option>
            <option>
                Euro
            </option>
        </select>
    </div>
    <div class=" mx-2"> 
        <a href="">Login</a>
    </div>

        {block name="frontend_index_checkout_actions_account"}
            <a href="{url controller='account'}"
               title="{"{if $userInfo}{s name="AccountGreetingBefore" namespace="frontend/account/sidebar"}{/s}{$userInfo['firstname']}{s name="AccountGreetingAfter" namespace="frontend/account/sidebar"}{/s} - {/if}{s namespace='frontend/index/checkout_actions' name='IndexLinkAccount'}{/s}"|escape}"
               aria-label="{"{if $userInfo}{s name="AccountGreetingBefore" namespace="frontend/account/sidebar"}{/s}{$userInfo['firstname']}{s name="AccountGreetingAfter" namespace="frontend/account/sidebar"}{/s} - {/if}{s namespace='frontend/index/checkout_actions' name='IndexLinkAccount'}{/s}"|escape}"
               class="btn is--icon-left entry--link account--link{if $userInfo} account--user-loggedin{/if}">
                <i class="icon--account"></i>
                {if $userInfo}
                    <span class="account--display navigation--personalized">
                        <span class="account--display-greeting">
                            {s name="AccountGreetingBefore" namespace="frontend/account/sidebar"}{/s}
                            {$userInfo['firstname']}
                            {s name="AccountGreetingAfter" namespace="frontend/account/sidebar"}{/s}
                        </span>
                        {s namespace='frontend/index/checkout_actions' name='IndexLinkAccount'}{/s}
                    </span>
                {else}
                    <span class="account--display">
                        {s namespace='frontend/index/checkout_actions' name='IndexLinkAccount'}{/s}
                    </span>
                {/if}
            </a>
        {/block}
{/block}