import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/capability_registry.dart';
import '../models/engineering_state.dart';
import '../models/git_status_snapshot.dart';

/// Workbench client — Projection for engineering state, Core for generic capability invoke.
class ForgeWorkbenchClient {
  ForgeWorkbenchClient({String? baseUrl})
      : baseUrl = baseUrl ??
            const String.fromEnvironment(
              'FORGE_CORE_URL',
              defaultValue: 'http://127.0.0.1:9470',
            );

  final String baseUrl;

  Future<CapabilityRegistry> fetchCapabilityRegistry() async {
    final response =
        await http.get(Uri.parse('$baseUrl/api/v1/core/capabilities'));
    if (response.statusCode != 200) {
      throw ForgeException(
        'Core registry unreachable (${response.statusCode}): ${response.body}',
      );
    }
    return CapabilityRegistry.fromJson(
      jsonDecode(response.body) as Map<String, dynamic>,
    );
  }

  Future<EngineeringState> fetchEngineeringState() async {
    final response =
        await http.get(Uri.parse('$baseUrl/api/v1/engineering/state'));
    if (response.statusCode != 200) {
      throw ForgeException(
        'Projection unreachable (${response.statusCode}): ${response.body}',
      );
    }
    return EngineeringState.fromJson(
      jsonDecode(response.body) as Map<String, dynamic>,
    );
  }

  Future<EngineeringDoc> fetchDoc(String slug) async {
    final response =
        await http.get(Uri.parse('$baseUrl/api/v1/engineering/docs/$slug'));
    if (response.statusCode != 200) {
      throw ForgeException('Doc fetch failed ($slug): ${response.body}');
    }
    return EngineeringDoc.fromJson(
      jsonDecode(response.body) as Map<String, dynamic>,
    );
  }

  Future<GitStatusSnapshot> observeGitStatus({String? repoPath}) async {
    final body = <String, dynamic>{};
    if (repoPath != null) {
      body['path'] = repoPath;
    }
    final response = await http.post(
      Uri.parse(
        '$baseUrl/api/v1/core/capabilities/core.git.status.read/invoke',
      ),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode(body),
    );
    if (response.statusCode != 200) {
      throw ForgeException('core.git.status.read failed: ${response.body}');
    }
    final payload = jsonDecode(response.body) as Map<String, dynamic>;
    final result = payload['result'] as Map<String, dynamic>?;
    if (result == null) {
      throw ForgeException('core.git.status.read returned no snapshot');
    }
    return GitStatusSnapshot.fromJson(result);
  }

  /// Intent → core.browser.url.open (generic URL only).
  Future<void> openBrowserUrl({required String url}) async {
    final response = await http.post(
      Uri.parse(
        '$baseUrl/api/v1/core/capabilities/core.browser.url.open/invoke',
      ),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'url': url}),
    );
    if (response.statusCode != 200) {
      throw ForgeException('core.browser.url.open failed: ${response.body}');
    }
  }

  /// Workbench maps intent → capability identity (arguments only, no action string).
  Future<void> openCursorApplication({required String repoPath}) async {
    final response = await http.post(
      Uri.parse(
        '$baseUrl/api/v1/core/capabilities/core.shell.application.open/invoke',
      ),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'application': 'cursor',
        'path': repoPath,
      }),
    );
    if (response.statusCode != 200) {
      throw ForgeException('core.shell.application.open failed: ${response.body}');
    }
  }
}

class ForgeException implements Exception {
  ForgeException(this.message);
  final String message;

  @override
  String toString() => message;
}
