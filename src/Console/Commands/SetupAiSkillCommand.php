<?php

namespace HasinHayder\Tyro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class SetupAiSkillCommand extends Command {
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tyro:setup-ai-skill';

    /**
     * The console command description.
     */
    protected $description = 'Install the Tyro Authorization AI skill for your preferred agent (Claude, Copilot, Codex, Gemini, Kilo)';

    /**
     * Universal agents.md skill discovery location.
     *
     * Always installed alongside the agent-specific directory so any
     * agent that follows the agents.md convention can find the skill.
     */
    public const UNIVERSAL_SKILL_DIR = '.agents/skills/tyro';

    /**
     * Mapping of AI agents to their agent-specific target skill directory.
     */
    protected array $agentTargets = [
        'kilo' => '.kilo/skills/tyro',
        'claude' => '.claude/skills/tyro',
        'github copilot' => '.github/skills/tyro',
        'codex' => '.codex/skills/tyro',
        'gemini' => '.gemini/skills/tyro',
        'laravel boost' => '.ai/skills/tyro',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int {
        $this->info('');
        $this->info('  ╔════════════════════════════════════════╗');
        $this->info('  ║   Tyro Authorization AI Skill Setup    ║');
        $this->info('  ╚════════════════════════════════════════╝');
        $this->info('');

        $sourcePath = $this->getSourceSkillPath();

        if (! is_dir($sourcePath)) {
            $this->error('   ✗ Source skill directory not found: '.$sourcePath);

            return self::FAILURE;
        }

        $agents = array_keys($this->agentTargets);
        $agents[] = 'all';

        $choice = $this->choice(
            'Which AI agent would you like to install the skill for?',
            $agents,
            0
        );

        $selectedAgents = $choice === 'all'
            ? array_keys($this->agentTargets)
            : [$choice];

        $ok = true;

        // Phase 1: install to each selected agent's specific discovery directory.
        foreach ($selectedAgents as $agent) {
            $relativePath = $this->agentTargets[$agent] ?? null;

            if (! $relativePath) {
                $this->warn("   ⚠ Unknown agent: {$agent}");
                $ok = false;
                continue;
            }

            if (! $this->installTo(base_path($relativePath), $sourcePath, $agent.': '.$relativePath)) {
                $ok = false;
            }
        }

        // Phase 2: install to the universal agents.md discovery directory exactly once.
        if (! $this->installTo(base_path(self::UNIVERSAL_SKILL_DIR), $sourcePath, 'universal: '.self::UNIVERSAL_SKILL_DIR)) {
            $ok = false;
        }

        $this->info('');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Install the skill directory at a specific target path.
     *
     * Strategy: stage the new contents in a sibling temp directory,
     * back up any existing target, then swap. If anything fails partway
     * the existing target can be restored from the backup.
     */
    protected function installTo(string $targetPath, string $sourcePath, string $label): bool {
        $filesystem = new Filesystem;

        if (! is_dir($targetPath)) {
            $filesystem->makeDirectory($targetPath, 0755, true);
            $this->info("   ✓ Created directory: {$targetPath}");
        }

        $staging = $targetPath.'.__installing__';
        $backup = $targetPath.'.__backup__';

        // Defensive: clear any stale staging/backup from a previous failed run.
        if (is_dir($staging)) {
            $filesystem->deleteDirectory($staging);
        }
        if (is_dir($backup)) {
            $filesystem->deleteDirectory($backup);
        }

        if (! $filesystem->copyDirectory($sourcePath, $staging)) {
            $this->error("   ✗ Failed to stage skill files for {$label}");
            $filesystem->deleteDirectory($staging);

            return false;
        }

        // Back up the existing target by renaming the directory itself,
        // which is atomic on the same filesystem.
        if (! @rename($targetPath, $backup)) {
            // Rename can fail if $targetPath == $backup (shouldn't happen) or
            // on some cross-device moves. Fall back to copy + delete.
            if (is_dir($targetPath) && ! $filesystem->copyDirectory($targetPath, $backup)) {
                $this->error("   ✗ Failed to back up existing install for {$label}");
                $filesystem->deleteDirectory($staging);

                return false;
            }
            if (is_dir($targetPath)) {
                $filesystem->deleteDirectory($targetPath);
            }
        }

        // Move the staged install into place.
        if (! @rename($staging, $targetPath)) {
            $this->error("   ✗ Failed to move staged install into place for {$label}; restoring backup");
            if (is_dir($targetPath)) {
                $filesystem->deleteDirectory($targetPath);
            }
            @rename($backup, $targetPath);
            $filesystem->deleteDirectory($staging);

            return false;
        }

        // Success — discard the backup.
        $filesystem->deleteDirectory($backup);
        $this->info("   ✓ Installed {$label}");

        return true;
    }

    /**
     * Get the source skill directory within the package.
     */
    protected function getSourceSkillPath(): string {
        return __DIR__.'/../../../skills/tyro';
    }
}
