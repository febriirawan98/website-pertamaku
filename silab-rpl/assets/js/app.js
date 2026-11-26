/* SILAB-RPL - skrip antarmuka */
(function () {
    'use strict';

    // Sidebar mobile
    var body = document.body;
    var toggle = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (toggle) toggle.addEventListener('click', function () { body.classList.toggle('sidebar-open'); });
    if (backdrop) backdrop.addEventListener('click', function () { body.classList.remove('sidebar-open'); });

    // Konfirmasi sebelum aksi (form dengan atribut data-confirm="pesan")
    var confirmEl = document.getElementById('confirmModal');
    var pendingForm = null;
    if (confirmEl && window.bootstrap) {
        var confirmModal = new bootstrap.Modal(confirmEl);
        document.addEventListener('submit', function (ev) {
            var f = ev.target;
            if (f.dataset && f.dataset.confirm && !f.dataset.confirmed) {
                ev.preventDefault();
                pendingForm = f;
                document.getElementById('confirmMessage').textContent = f.dataset.confirm;
                var ok = document.getElementById('confirmOk');
                ok.textContent = f.dataset.confirmOk || 'Ya, lanjutkan';
                confirmModal.show();
            }
        });
        document.getElementById('confirmOk').addEventListener('click', function () {
            if (pendingForm) {
                pendingForm.dataset.confirmed = '1';
                confirmModal.hide();
                pendingForm.submit();
            }
        });
    }

    // Modal tambah/ubah serbaguna: tombol dengan data-fill='{"id":1,"nama":"..."}'
    document.querySelectorAll('.js-form-modal').forEach(function (modalEl) {
        modalEl.addEventListener('show.bs.modal', function (ev) {
            var btn = ev.relatedTarget;
            var form = modalEl.querySelector('form');
            form.reset();
            var fill = btn && btn.dataset.fill ? JSON.parse(btn.dataset.fill) : {};
            var title = modalEl.querySelector('.modal-title');
            if (title && btn) title.textContent = btn.dataset.title || title.textContent;
            form.querySelectorAll('[name]').forEach(function (inp) {
                if (inp.name === '_token') return;
                if (inp.type === 'hidden' && !fill.hasOwnProperty(inp.name)) return; // biarkan nilai bawaan (mis. action)
                var v =fill.hasOwnProperty(inp.name) ? fill[inp.name] : (inp.dataset.default || '');
                if (inp.type === 'checkbox') inp.checked = !!parseInt(v || 0, 10);
                else inp.value = v === null ? '' : v;
            });
            modalEl.querySelectorAll('[data-show-edit]').forEach(function (el) {
                el.classList.toggle('d-none', !fill.id);
            });
            modalEl.querySelectorAll('[data-show-new]').forEach(function (el) {
                el.classList.toggle('d-none', !!fill.id);
            });
        });
    });

    // Laporan: tampilkan hanya filter yang relevan
    var reportType = document.getElementById('reportType');
    if (reportType) {
        var apply = function () {
            var filters = (reportType.selectedOptions[0].dataset.filters || '').split(',');
            document.querySelectorAll('[data-filter]').forEach(function (el) {
                el.classList.toggle('d-none', filters.indexOf(el.dataset.filter) === -1);
            });
        };
        reportType.addEventListener('change', apply);
        apply();
    }

    // Peminjaman / transaksi: tampilkan info stok saat memilih barang
    var itemSel = document.querySelector('select[data-stock-info]');
    var infoBox = document.getElementById('stockInfo');
    if (itemSel && infoBox) {
        var show = function () {
            var o = itemSel.selectedOptions[0];
            if (!o || !o.value) { infoBox.classList.add('d-none'); return; }
            infoBox.classList.remove('d-none');
            infoBox.innerHTML = 'Fisik: <b>' + o.dataset.total + '</b> &middot; Dipinjam: <b>' + o.dataset.borrowed +
                '</b> &middot; Pemeliharaan: <b>' + o.dataset.maint + '</b> &middot; Tersedia: <b>' + o.dataset.avail + '</b> ' + (o.dataset.unit || '');
            var qty = document.querySelector('input[data-max-from-stock]');
            if (qty && qty.dataset.maxFromStock === 'avail') qty.max = o.dataset.avail;
        };
        itemSel.addEventListener('change', show);
        show();
    }

    // Transaksi: ubah label sesuai jenis (penyesuaian memakai stok fisik hasil pemeriksaan)
    var jenis = document.getElementById('jenisTrx');
    if (jenis) {
        var upd = function () {
            var adj = jenis.value === 'penyesuaian';
            document.querySelectorAll('[data-when-adjust]').forEach(function (el) { el.classList.toggle('d-none', !adj); });
            document.querySelectorAll('[data-when-normal]').forEach(function (el) { el.classList.toggle('d-none', adj); });
            var norm = document.getElementById('jumlahInput'), adjI = document.getElementById('stokBaruInput');
            if (norm) norm.required = !adj;
            if (adjI) adjI.required = adj;
        };
        jenis.addEventListener('change', upd);
        upd();
    }
})();
