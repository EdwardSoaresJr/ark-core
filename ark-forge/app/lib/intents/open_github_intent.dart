import '../models/engineering_state.dart';

/// Workbench intent: Open GitHub for the active repository.
/// Maps human intent → capability arguments. Core receives a generic URL only.
class OpenGitHubIntent {
  const OpenGitHubIntent._();

  static String? resolveUrl(EngineeringState state) => state.repositoryWebUrl;
}
