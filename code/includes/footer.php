<?php
/**
 * TeamForge Common Footer
 */
?>
<?php if (isLoggedIn()): ?>
        </main>
        <footer class="text-center py-3 border-top bg-white text-muted small">
            <div class="container-fluid">
                <span>&copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong> — Intelligent Student Team Formation Platform. Classical AI & Multi-Agent Heuristics.</span>
            </div>
        </footer>
    </div>
</div>
<?php else: ?>
    </div>
<?php endif; ?>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- TeamForge Core App JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
