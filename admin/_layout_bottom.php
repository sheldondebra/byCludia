      </main>

      <!-- Footer -->
      <footer class="border-t border-stone-200/80 bg-white/60">
        <div class="px-6 sm:px-10 py-4 flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <span class="w-9 h-9 rounded-full bg-stone-900 text-[#F3C4C4] font-semibold text-xs flex items-center justify-center shrink-0"><?= e($adminInitials) ?></span>
            <div class="min-w-0">
              <p class="text-sm font-medium truncate"><?= e($adminName) ?></p>
              <p class="text-xs text-stone-400 truncate"><?= e($storeName) ?> admin</p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-stone-400">
            <span>&copy; <?= date('Y') ?> <?= e($storeName) ?></span>
            <a href="../index.php" target="_blank" class="hover:text-stone-700 transition">View store</a>
            <a href="settings.php" class="hover:text-stone-700 transition">Settings</a>
            <a href="<?= e(url('logout')) ?>" class="hover:text-rose-600 transition">Logout</a>
          </div>
        </div>
      </footer>
    </div>
  </div>
  <script>
    if (window.lucide) { lucide.createIcons(); }
  </script>
</body>
</html>
