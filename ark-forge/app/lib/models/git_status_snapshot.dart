class GitStatusSnapshot {
  GitStatusSnapshot({
    required this.branch,
    required this.dirty,
    required this.modifiedCount,
    required this.ahead,
    required this.behind,
    required this.modifiedFiles,
    required this.observedAt,
  });

  factory GitStatusSnapshot.fromJson(Map<String, dynamic> json) {
    return GitStatusSnapshot(
      branch: json['branch'] as String,
      dirty: json['dirty'] as bool,
      modifiedCount: json['modified_count'] as int,
      ahead: json['ahead'] as int? ?? 0,
      behind: json['behind'] as int? ?? 0,
      modifiedFiles: (json['modified_files'] as List<dynamic>? ?? [])
          .map((e) => e as String)
          .toList(),
      observedAt: json['observed_at'] as String,
    );
  }

  final String branch;
  final bool dirty;
  final int modifiedCount;
  final int ahead;
  final int behind;
  final List<String> modifiedFiles;
  final String observedAt;
}
