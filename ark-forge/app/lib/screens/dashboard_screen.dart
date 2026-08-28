import 'package:flutter/material.dart';

import '../intents/open_github_intent.dart';
import '../models/capability_registry.dart';
import '../models/engineering_state.dart';
import '../services/forge_workbench_client.dart';
import '../theme/forge_theme.dart';
import '../widgets/status_tile.dart';
import 'engineering_doc_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.client});

  final ForgeWorkbenchClient client;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  EngineeringState? _state;
  CapabilityRegistry? _registry;
  String? _error;
  bool _loading = true;
  bool _opening = false;
  GitObserve? _gitObserve;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  Future<void> _refresh() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        widget.client.fetchEngineeringState(),
        widget.client.fetchCapabilityRegistry(),
      ]);
      if (!mounted) return;
      setState(() {
        _state = results[0] as EngineeringState;
        _registry = results[1] as CapabilityRegistry;
        _loading = false;
      });
    } on ForgeException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _openCursor() async {
    final repoPath = _state?.repoPath;
    if (repoPath == null) return;

    setState(() => _opening = true);
    try {
      await widget.client.openCursorApplication(repoPath: repoPath);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('core.shell.application.open'),
          ),
        );
      }
    } on ForgeException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message)),
        );
      }
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  Future<void> _openGitHub() async {
    final state = _state;
    if (state == null) return;

    final url = OpenGitHubIntent.resolveUrl(state);
    if (url == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No repository web URL available')),
        );
      }
      return;
    }

    setState(() => _opening = true);
    try {
      await widget.client.openBrowserUrl(url: url);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('core.browser.url.open')),
        );
      }
    } on ForgeException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message)),
        );
      }
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  Future<void> _runGitStatus() async {
    setState(() => _opening = true);
    try {
      final snapshot = await widget.client.observeGitStatus(
        repoPath: _state?.repoPath,
      );
      if (!mounted) return;
      setState(() {
        _gitObserve = GitObserve.fromSnapshot(snapshot);
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'core.git.status.read · ${_gitObserve!.branch} · observed ${_gitObserve!.observedAt}',
            ),
          ),
        );
      }
    } on ForgeException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message)),
        );
      }
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  void _openAuthority() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => EngineeringAuthorityScreen(client: widget.client),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Container(width: 8, height: 24, color: ForgeTheme.arkBlue),
            const SizedBox(width: 12),
            const Text(
              'ARK Forge',
              style: TextStyle(fontWeight: FontWeight.w700, letterSpacing: 0.5),
            ),
            const SizedBox(width: 8),
            Text(
              'Workbench',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w400,
                color: ForgeTheme.textMuted.withValues(alpha: 0.9),
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            onPressed: _loading ? null : _refresh,
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _ErrorPanel(message: _error!, onRetry: _refresh)
              : _DashboardBody(
                  state: _state!,
                  registry: _registry!,
                  gitObserve: _gitObserve,
                  opening: _opening,
                  onOpenCursor: _openCursor,
                  onOpenGitHub: _openGitHub,
                  onRunGitStatus: _runGitStatus,
                  onOpenAuthority: _openAuthority,
                  onOpenDoc: (slug, title) {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => EngineeringDocScreen(
                          client: widget.client,
                          slug: slug,
                          title: title,
                        ),
                      ),
                    );
                  },
                ),
    );
  }
}

class _ErrorPanel extends StatelessWidget {
  const _ErrorPanel({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 480),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.memory, size: 48, color: ForgeTheme.warning),
            const SizedBox(height: 16),
            Text(
              'Forge Core unreachable',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: ForgeTheme.textMuted),
            ),
            const SizedBox(height: 8),
            const Text(
              'Start ARK Bridge from the Start Menu, or install ARK Forge Setup.',
              style: TextStyle(color: ForgeTheme.textMuted, fontSize: 12),
            ),
            const Text(
              'Forge Core runs automatically when Bridge starts.',
              style: TextStyle(color: ForgeTheme.textMuted, fontSize: 12),
            ),
            const SizedBox(height: 20),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

class _DashboardBody extends StatelessWidget {
  const _DashboardBody({
    required this.state,
    required this.registry,
    required this.gitObserve,
    required this.opening,
    required this.onOpenCursor,
    required this.onOpenGitHub,
    required this.onRunGitStatus,
    required this.onOpenAuthority,
    required this.onOpenDoc,
  });

  final EngineeringState state;
  final CapabilityRegistry registry;
  final GitObserve? gitObserve;
  final bool opening;
  final VoidCallback onOpenCursor;
  final VoidCallback onOpenGitHub;
  final VoidCallback onRunGitStatus;
  final VoidCallback onOpenAuthority;
  final void Function(String slug, String title) onOpenDoc;

  @override
  Widget build(BuildContext context) {
    final review = state.recentReview;
    final git = gitObserve ?? state.git;
    final gitSubtitle = git.clean
        ? 'Branch ${git.branch} · ${git.repositoryHealth}'
        : '${git.modifiedCount} modified · ${git.branch} · ${git.repositoryHealth}'
            '${git.ahead > 0 || git.behind > 0 ? ' · ↑${git.ahead} ↓${git.behind}' : ''}'
            '${git.observedAt.isNotEmpty ? '\nObserved ${git.observedAt}' : ''}';

    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 720),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ForgeCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    GestureDetector(
                      behavior: HitTestBehavior.opaque,
                      onTap: () => onOpenDoc(
                        state.milestone.docSlug,
                        'Current Milestone',
                      ),
                      child: StatusTile(
                        label: 'Current Milestone',
                        value: state.milestone.title,
                        subtitle: 'Tap to read authority',
                      ),
                    ),
                    const Divider(height: 24),
                    GestureDetector(
                      behavior: HitTestBehavior.opaque,
                      onTap: () => onOpenDoc('active-pr', 'Active PR'),
                      child: StatusTile(
                        label: 'Active PR',
                        value: state.activePr.id,
                        subtitle: 'Status: ${state.activePr.status}',
                      ),
                    ),
                    const Divider(height: 24),
                    StatusTile(
                      label: 'Git',
                      value: git.repositoryHealth,
                      subtitle: gitSubtitle,
                      indicator: IndicatorDot(
                        color: git.clean
                            ? ForgeTheme.success
                            : ForgeTheme.warning,
                      ),
                    ),
                    const Divider(height: 24),
                    StatusTile(
                      label: 'Cursor',
                      value: state.workstation.cursorRunning
                          ? 'Running'
                          : 'Not detected',
                      indicator: IndicatorDot(
                        color: state.workstation.cursorRunning
                            ? ForgeTheme.success
                            : ForgeTheme.textMuted,
                      ),
                    ),
                    const Divider(height: 24),
                    StatusTile(
                      label: 'Architecture',
                      value: state.architecture.status,
                    ),
                    if (review != null) ...[
                      const Divider(height: 24),
                      StatusTile(
                        label: 'Recent Review',
                        value: review.title,
                        subtitle: '${review.status} · ${review.date}',
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Text(
                'Forge Core',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: ForgeTheme.textMuted,
                      letterSpacing: 1.2,
                    ),
              ),
              const SizedBox(height: 8),
              Text(
                '${registry.capabilities.length} capabilities · '
                '${registry.invokeCount} invoke · '
                '${registry.observeCount} observe',
                style: const TextStyle(color: ForgeTheme.textMuted, fontSize: 12),
              ),
              Text(
                registry.registryCapabilityId,
                style: const TextStyle(color: ForgeTheme.textMuted, fontSize: 11),
              ),
              const SizedBox(height: 20),
              Text(
                'Workspace',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: ForgeTheme.textMuted,
                      letterSpacing: 1.2,
                    ),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  _WorkspaceButton(
                    label: 'Open Cursor',
                    subtitle: 'core.shell.application.open',
                    icon: Icons.code,
                    loading: opening,
                    enabled: true,
                    onPressed: onOpenCursor,
                  ),
                  _WorkspaceButton(
                    label: 'Open GitHub',
                    subtitle: 'core.browser.url.open',
                    icon: Icons.hub_outlined,
                    loading: opening,
                    enabled: state.repositoryWebUrl != null,
                    onPressed: onOpenGitHub,
                  ),
                  _WorkspaceButton(
                    label: 'Run Git Status',
                    subtitle: 'core.git.status.read',
                    icon: Icons.account_tree_outlined,
                    loading: opening,
                    enabled: true,
                    onPressed: onRunGitStatus,
                  ),
                  _WorkspaceButton(
                    label: 'Engineering Authority',
                    icon: Icons.menu_book_outlined,
                    loading: false,
                    enabled: true,
                    onPressed: onOpenAuthority,
                  ),
                  _WorkspaceButton(
                    label: 'Open Terminal',
                    icon: Icons.terminal,
                    loading: false,
                    enabled: false,
                    onPressed: () {},
                  ),
                  _WorkspaceButton(
                    label: 'Open Browser',
                    icon: Icons.open_in_browser,
                    loading: false,
                    enabled: false,
                    onPressed: () {},
                  ),
                ],
              ),
              const SizedBox(height: 24),
              Text(
                state.repoPath,
                style: const TextStyle(
                  color: ForgeTheme.textMuted,
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _WorkspaceButton extends StatelessWidget {
  const _WorkspaceButton({
    required this.label,
    this.subtitle,
    required this.icon,
    required this.loading,
    required this.enabled,
    required this.onPressed,
  });

  final String label;
  final String? subtitle;
  final IconData icon;
  final bool loading;
  final bool enabled;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton(
      onPressed: (loading || !enabled) ? null : onPressed,
      style: OutlinedButton.styleFrom(
        foregroundColor: ForgeTheme.textPrimary,
        side: const BorderSide(color: ForgeTheme.border),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label),
              if (subtitle != null)
                Text(
                  subtitle!,
                  style: const TextStyle(
                    fontSize: 10,
                    color: ForgeTheme.textMuted,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
