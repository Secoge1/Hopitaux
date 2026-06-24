<?php
/**
 * Menus Actions — injection script dans le <head> (obligatoire pour onclick inline).
 */
if (!function_exists('app_render_mod_actions_script')) {
    function app_render_mod_actions_script(): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        $jsPath = dirname(__DIR__) . '/assets/js/app-mod-actions.js';
        if (is_file($jsPath)) {
            $js = file_get_contents($jsPath);
            if ($js !== false && $js !== '') {
                $js = str_replace('</script>', '<\\/script>', $js);
                echo '<script>' . "\n" . $js . "\n" . '</script>';
                return;
            }
        }

        /* Fallback autonome si le fichier JS est absent sur le serveur */
        ?>
<script>
window.AppModActions=window.AppModActions||{};
(function(){
    var active=null,guard=0;
    function restore(m,d){if(!m)return;var h=m._modActionsHome||d;if(h&&m.parentElement===document.body)h.appendChild(m);m.classList.remove('mod-actions-menu--portal','show');m.style.cssText='';if(d)d.classList.remove('show');}
    function closeAll(){if(active){restore(active.menu,active.dropdown);active.toggle.setAttribute('aria-expanded','false');active=null;return;}document.querySelectorAll('.dropdown.mod-actions .dropdown-menu.show').forEach(function(m){var d=m._modActionsHome||m.closest('.dropdown.mod-actions');restore(m,d);if(d){var t=d.querySelector('.mod-actions-btn');if(t)t.setAttribute('aria-expanded','false');}});}
    function openMenu(btn,d,m){closeAll();m._modActionsHome=d;m.classList.add('show');d.classList.add('show');btn.setAttribute('aria-expanded','true');if(m.parentElement!==document.body)document.body.appendChild(m);m.classList.add('mod-actions-menu--portal');var r=btn.getBoundingClientRect(),w=m.offsetWidth||210,h=m.offsetHeight||120,l=Math.max(8,r.right-w),t=r.bottom+4;if(t+h>window.innerHeight-8)t=Math.max(8,r.top-h-4);m.style.cssText='position:fixed;display:block;visibility:visible;opacity:1;z-index:9999;top:'+t+'px;left:'+l+'px;margin:0;';active={toggle:btn,menu:m,dropdown:d};guard=Date.now()+150;}
    function toggle(btn,ev){if(ev){ev.preventDefault();ev.stopPropagation();}var d=btn&&btn.closest&&btn.closest('.dropdown.mod-actions');if(!d)return false;var m=d.querySelector('.dropdown-menu.mod-actions-menu')||d.querySelector('.dropdown-menu');if(!m)return false;if(active&&active.menu===m&&m.classList.contains('show')){closeAll();return false;}openMenu(btn,d,m);return false;}
    function handleDeleteTrigger(trigger,ev){if(ev){ev.preventDefault();ev.stopPropagation();}closeAll();var id=parseInt(trigger.getAttribute('data-delete-id')||'0',10),name=trigger.getAttribute('data-delete-name')||'';if(typeof window.confirmDelete==='function'&&id)setTimeout(function(){window.confirmDelete(id,name);},10);}
    window.AppModActions.toggle=toggle;
    window.AppModActions.closeAll=closeAll;
    window.AppModActions.scan=window.AppModActions.scan||function(){};
    document.addEventListener('click',function(e){var del=e.target.closest('.js-mod-delete-trigger');if(del&&del.closest('.mod-actions-menu')){handleDeleteTrigger(del,e);return;}if(e.target.closest('.mod-actions-menu .dropdown-item'))setTimeout(closeAll,0);},true);
    document.addEventListener('click',function(e){if(Date.now()<guard)return;if(e.target.closest('.mod-actions-btn')||e.target.closest('.mod-actions-menu'))return;closeAll();});
    document.addEventListener('keydown',function(e){if(e.key==='Escape')closeAll();});
})();
</script>
        <?php
    }
}
