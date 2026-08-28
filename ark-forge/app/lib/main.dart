import 'package:flutter/material.dart';

import 'screens/dashboard_screen.dart';
import 'services/forge_workbench_client.dart';
import 'theme/forge_theme.dart';

void main() {
  runApp(ForgeApp(client: ForgeWorkbenchClient()));
}

class ForgeApp extends StatelessWidget {
  const ForgeApp({super.key, required this.client});

  final ForgeWorkbenchClient client;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'ARK Forge',
      theme: ForgeTheme.dark(),
      home: DashboardScreen(client: client),
      debugShowCheckedModeBanner: false,
    );
  }
}
