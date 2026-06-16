// Enhanced frontend i18n loader with dynamic DOM translation and IndexedDB caching
window.I18N = (function(){
    const cache = {};
    let active = (document.cookie.match(/(^|;)\s*lang=([^;]+)/)?.pop() || 'en');

    async function load(lang, section='dashboard'){
        const key = lang+':'+section;
        if(cache[key]) return cache[key];
        // try IndexedDB cache first
        try{
            if(window.I18NStorage){
                const stored = await window.I18NStorage.get(key);
                if(stored){ cache[key] = stored; return stored; }
            }
        }catch(e){ /* ignore */ }

        const res = await fetch(`/api/v1/i18n?lang=${lang}&section=${section}`);
        if(!res.ok) return {};
        const j = await res.json();
        cache[key] = j.translations || {};

        try{ if(window.I18NStorage) await window.I18NStorage.put(key, cache[key]); }catch(e){}

        return cache[key];
    }

    function t(key, section='dashboard'){
        const lang = active || (document.cookie.match(/(^|;)\s*lang=([^;]+)/)?.pop() || 'en');
        const keyFull = lang+':'+section;
        const translations = cache[keyFull] || {};
        return translations[key] || key;
    }

    async function translatePage(){
        const nodes = document.querySelectorAll('[data-i18n]');
        const toLoad = {};
        nodes.forEach(n=>{
            const section = n.getAttribute('data-i18n-section') || 'dashboard';
            const key = n.getAttribute('data-i18n');
            const mapKey = active+':'+section;
            if(!cache[mapKey]) toLoad[mapKey] = {lang: active, section};
        });

        // load sections
        const loads = Object.values(toLoad).map(x=>load(x.lang, x.section));
        await Promise.all(loads);

        // apply translations
        nodes.forEach(n=>{
            const section = n.getAttribute('data-i18n-section') || 'dashboard';
            const key = n.getAttribute('data-i18n');
            const attr = n.getAttribute('data-i18n-attr') || 'text';
            const text = t(key, section);
            if(attr === 'text') n.textContent = text;
            else n.setAttribute(attr, text);
        });
    }

    function setActive(lang){
        active = lang;
        document.cookie = `lang=${lang}; path=/; max-age=${60*60*24*365}`;
    }

    async function setLanguage(lang){
        // call server API to persist (session / DB)
        const res = await fetch('/api/v1/i18n/change.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `lang=${encodeURIComponent(lang)}`
        });
        if(!res.ok) throw new Error('Failed to change language');
        const j = await res.json();
        if(j.success){
            setActive(j.lang);
            await translatePage();
            return true;
        }
        return false;
    }

    return { load, t, cache, active: active, setActive, setLanguage, translatePage };
})();
