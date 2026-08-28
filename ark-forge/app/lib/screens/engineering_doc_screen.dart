import 'package:flutter/material.dart';
import 'package:flutter_markdown/flutter_markdown.dart';

import '../services/forge_workbench_client.dart';
import '../theme/forge_theme.dart';

class EngineeringDocScreen extends StatefulWidget {
  const EngineeringDocScreen({
    super.key,
    required this.client,
    required this.slug,
    required this.title,
  });

  final ForgeWorkbenchClient client;
  final String slug;
  final String title;

  @override
  State<EngineeringDocScreen> createState() => _EngineeringDocScreenState();
}

class _EngineeringDocScreenState extends State<EngineeringDocScreen> {
  EngineeringDoc? _doc;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final doc = await widget.client.fetchDoc(widget.slug);
      if (!mounted) return;
      setState(() {
        _doc = doc;
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          IconButton(onPressed: _load, icon: const Icon(Icons.refresh)),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Text(
                    _error!,
                    style: const TextStyle(color: ForgeTheme.warning),
                  ),
                )
              : Markdown(
                  data: _doc!.content,
                  styleSheet: MarkdownStyleSheet(
                    h1: const TextStyle(
                      color: ForgeTheme.textPrimary,
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                    ),
                    p: const TextStyle(
                      color: ForgeTheme.textPrimary,
                      fontSize: 14,
                      height: 1.5,
                    ),
                    code: const TextStyle(
                      color: ForgeTheme.arkBlue,
                      fontFamily: 'Consolas',
                    ),
                  ),
                ),
    );
  }
}

class EngineeringAuthorityScreen extends StatelessWidget {
  const EngineeringAuthorityScreen({super.key, required this.client});

  final ForgeWorkbenchClient client;

  static const _docs = [
    ('current-milestone', 'Current Milestone'),
    ('active-pr', 'Active PR'),
    ('roadmap', 'Roadmap'),
    ('implementation-log', 'Implementation Log'),
    ('standards', 'Standards'),
    ('architecture', 'Architecture'),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Engineering Authority')),
      body: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: _docs.length,
        separatorBuilder: (_, __) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final (slug, title) = _docs[index];
          return ListTile(
            title: Text(title),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => EngineeringDocScreen(
                    client: client,
                    slug: slug,
                    title: title,
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
