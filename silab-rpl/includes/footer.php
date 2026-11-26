        </main>
        <footer class="app-footer no-print">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; <?= e(SCHOOL_NAME) ?>
        </footer>
    </div>
</div>

<!-- Modal konfirmasi umum (hapus / aksi penting) -->
<div class="modal fade no-print" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-question-circle me-2 text-warning"></i>Konfirmasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="confirmMessage">Yakin?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmOk">Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
