/**
 * Warehouse calendar v2 — tasks, receiving, dispatch, transfers, expiry, counts
 */
document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('whCalGrid');
    if (!grid) return;

    const { t, esc, showError, hideError, updateLastUpdated } = WarehouseUI;
    const locale = () => window.WH_CONFIG?.locale || 'fr-FR';

    const state = {
        cursor: new Date(),
        filterType: '',
        data: null,
        selectedDate: null,
    };

    const els = {
        label: document.getElementById('whCalLabel'),
        heroMeta: document.getElementById('whCalHeroMeta'),
        loading: document.getElementById('whCalLoading'),
        weekdays: document.getElementById('whCalWeekdays'),
        dayEvents: document.getElementById('whCalDayEvents'),
        dayEmpty: document.getElementById('whCalDayEmpty'),
        selectedDate: document.getElementById('whCalSelectedDate'),
        statTotal: document.getElementById('whCalStatTotal'),
        statTasks: document.getElementById('whCalStatTasks'),
        statReceiving: document.getElementById('whCalStatReceiving'),
        statDispatch: document.getElementById('whCalStatDispatch'),
        statExpiry: document.getElementById('whCalStatExpiry'),
    };

    const weekdayKeys = [
        'wh_cal_weekday_mon', 'wh_cal_weekday_tue', 'wh_cal_weekday_wed',
        'wh_cal_weekday_thu', 'wh_cal_weekday_fri', 'wh_cal_weekday_sat', 'wh_cal_weekday_sun',
    ];

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function dateKey(y, m, d) {
        return `${y}-${pad(m + 1)}-${pad(d)}`;
    }

    function formatDayLabel(iso) {
        if (!iso) return '—';
        const d = new Date(`${iso}T12:00:00`);
        return d.toLocaleDateString(locale(), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }

    function typeLabel(type) {
        const map = {
            task: t('wh_cal_type_task'),
            receiving: t('wh_cal_type_receiving'),
            dispatch: t('wh_cal_type_dispatch'),
            transfer: t('wh_cal_type_transfer'),
            expiry: t('wh_cal_type_expiry'),
            count: t('wh_cal_type_count'),
        };
        return map[type] || type;
    }

    function renderWeekdays() {
        if (!els.weekdays) return;
        els.weekdays.innerHTML = weekdayKeys.map((key) => `<span class="wh-cal-weekday">${esc(t(key))}</span>`).join('');
    }

    function updateStats(summary) {
        const s = summary || {};
        const set = (el, v) => { if (el) { el.textContent = String(v ?? 0); el.classList.remove('is-loading'); } };
        set(els.statTotal, s.total);
        set(els.statTasks, s.task);
        set(els.statReceiving, s.receiving);
        set(els.statDispatch, s.dispatch);
        set(els.statExpiry, s.expiry);
        if (els.heroMeta) {
            els.heroMeta.textContent = `${s.total ?? 0} ${t('wh_cal_stat_total').toLowerCase()}`;
        }
    }

    function renderGrid(byDay) {
        const y = state.cursor.getFullYear();
        const m = state.cursor.getMonth();
        if (els.label) {
            els.label.textContent = state.cursor.toLocaleDateString(locale(), { month: 'long', year: 'numeric' });
        }

        const first = new Date(y, m, 1);
        const startPad = (first.getDay() + 6) % 7; // Monday start
        const daysInMonth = new Date(y, m + 1, 0).getDate();
        const todayKey = dateKey(new Date().getFullYear(), new Date().getMonth(), new Date().getDate());

        const cells = [];
        for (let i = 0; i < startPad; i += 1) {
            cells.push('<div class="wh-cal-cell wh-cal-cell--pad" aria-hidden="true"></div>');
        }
        for (let d = 1; d <= daysInMonth; d += 1) {
            const key = dateKey(y, m, d);
            const events = byDay[key] || [];
            const isToday = key === todayKey;
            const isSelected = key === state.selectedDate;
            const dots = events.slice(0, 4).map((ev) => `<i class="wh-cal-dot wh-cal-dot--${esc(ev.type)}" title="${esc(typeLabel(ev.type))}"></i>`).join('');
            const more = events.length > 4 ? `<span class="wh-cal-more">+${events.length - 4}</span>` : '';
            cells.push(`
                <button type="button" class="wh-cal-cell${isToday ? ' is-today' : ''}${isSelected ? ' is-selected' : ''}${events.length ? ' has-events' : ''}"
                    data-date="${key}" aria-label="${d}">
                    <span class="wh-cal-day">${d}</span>
                    <span class="wh-cal-dots">${dots}${more}</span>
                    ${events.length ? `<span class="wh-cal-count">${events.length}</span>` : ''}
                </button>`);
        }
        grid.innerHTML = cells.join('');

        if (!state.selectedDate) {
            state.selectedDate = todayKey.slice(0, 7) === `${y}-${pad(m + 1)}` ? todayKey : dateKey(y, m, 1);
        }
        renderDayDetail(byDay);
    }

    function renderDayDetail(byDay) {
        const events = byDay[state.selectedDate] || [];
        if (els.selectedDate) els.selectedDate.textContent = formatDayLabel(state.selectedDate);
        if (!els.dayEvents) return;

        if (!events.length) {
            els.dayEvents.innerHTML = '';
            if (els.dayEmpty) els.dayEmpty.hidden = false;
            return;
        }
        if (els.dayEmpty) els.dayEmpty.hidden = true;

        els.dayEvents.innerHTML = events.map((ev) => `
            <li class="wh-cal-event wh-cal-event--${esc(ev.type)}">
                <div class="wh-cal-event__main">
                    <span class="wh-cal-dot wh-cal-dot--${esc(ev.type)}"></span>
                    <div>
                        <strong>${esc(ev.title)}</strong>
                        <span class="wh-muted">${esc(typeLabel(ev.type))}${ev.subtitle ? ` · ${esc(ev.subtitle)}` : ''}</span>
                    </div>
                </div>
                ${ev.href ? `<a href="${esc(ev.href)}" class="wh-cal-event__link">${esc(t('wh_cal_open_module'))}</a>` : ''}
            </li>`).join('');
    }

    async function load() {
        hideError();
        if (els.loading) els.loading.hidden = false;
        try {
            const y = state.cursor.getFullYear();
            const m = state.cursor.getMonth() + 1;
            const types = state.filterType || '';
            const res = await AdminAPI.getWarehousePortalCalendar(y, m, types);
            if (res.status !== 'success') throw new Error(res.message);
            const payload = res.data || {};
            if (payload.module_ready === false) {
                throw new Error(t('load_error'));
            }
            state.data = payload;
            updateStats(state.data.summary);
            renderGrid(state.data.by_day || {});
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            grid.innerHTML = '';
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    document.getElementById('whCalPrev')?.addEventListener('click', () => {
        state.cursor.setMonth(state.cursor.getMonth() - 1);
        state.selectedDate = null;
        load();
    });
    document.getElementById('whCalNext')?.addEventListener('click', () => {
        state.cursor.setMonth(state.cursor.getMonth() + 1);
        state.selectedDate = null;
        load();
    });
    document.getElementById('whCalToday')?.addEventListener('click', () => {
        state.cursor = new Date();
        state.selectedDate = dateKey(state.cursor.getFullYear(), state.cursor.getMonth(), state.cursor.getDate());
        load();
    });

    document.getElementById('whCalFilters')?.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-type]');
        if (!chip) return;
        state.filterType = chip.dataset.type || '';
        document.querySelectorAll('#whCalFilters .wh-cal-chip').forEach((c) => {
            c.classList.toggle('is-active', c === chip);
        });
        load();
    });

    grid.addEventListener('click', (e) => {
        const cell = e.target.closest('[data-date]');
        if (!cell) return;
        state.selectedDate = cell.dataset.date;
        renderGrid(state.data?.by_day || {});
    });

    document.addEventListener('wh:refresh', load);

    renderWeekdays();
    state.selectedDate = dateKey(state.cursor.getFullYear(), state.cursor.getMonth(), state.cursor.getDate());
    load();
});
