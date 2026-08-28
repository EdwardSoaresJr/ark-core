import 'git_status_snapshot.dart';

class EngineeringState {
  EngineeringState({
    required this.repoPath,
    required this.generatedAt,
    required this.milestone,
    required this.activePr,
    required this.git,
    required this.workstation,
    required this.architecture,
    this.recentReview,
    this.repositoryWebUrl,
  });

  factory EngineeringState.fromJson(Map<String, dynamic> json) {
    return EngineeringState(
      repoPath: json['repo_path'] as String,
      generatedAt: json['generated_at'] as String,
      milestone: MilestoneProjection.fromJson(
        json['milestone'] as Map<String, dynamic>,
      ),
      activePr: ActivePrProjection.fromJson(
        json['active_pr'] as Map<String, dynamic>,
      ),
      git: GitObserve.fromJson(json['git'] as Map<String, dynamic>),
      workstation: WorkstationProjection.fromJson(
        json['workstation'] as Map<String, dynamic>,
      ),
      architecture: ArchitectureProjection.fromJson(
        json['architecture'] as Map<String, dynamic>,
      ),
      recentReview: json['recent_review'] != null
          ? ReviewProjection.fromJson(
              json['recent_review'] as Map<String, dynamic>,
            )
          : null,
      repositoryWebUrl: json['repository_web_url'] as String?,
    );
  }

  final String repoPath;
  final String generatedAt;
  final MilestoneProjection milestone;
  final ActivePrProjection activePr;
  final GitObserve git;
  final WorkstationProjection workstation;
  final ArchitectureProjection architecture;
  final ReviewProjection? recentReview;
  final String? repositoryWebUrl;
}

class MilestoneProjection {
  MilestoneProjection({required this.title, required this.docSlug});

  factory MilestoneProjection.fromJson(Map<String, dynamic> json) {
    return MilestoneProjection(
      title: json['title'] as String,
      docSlug: json['doc_slug'] as String,
    );
  }

  final String title;
  final String docSlug;
}

class ActivePrProjection {
  ActivePrProjection({required this.id, required this.status});

  factory ActivePrProjection.fromJson(Map<String, dynamic> json) {
    return ActivePrProjection(
      id: json['id'] as String,
      status: json['status'] as String,
    );
  }

  final String id;
  final String status;
}

class GitObserve {
  GitObserve({
    required this.branch,
    required this.clean,
    required this.repositoryHealth,
    required this.modifiedCount,
    required this.ahead,
    required this.behind,
    required this.modifiedFiles,
    required this.observedAt,
  });

  factory GitObserve.fromJson(Map<String, dynamic> json) {
    return GitObserve(
      branch: json['branch'] as String,
      clean: json['clean'] as bool,
      repositoryHealth: json['repository_health'] as String? ??
          (json['clean'] as bool ? 'Clean' : 'Modified'),
      modifiedCount: json['modified_count'] as int,
      ahead: json['ahead'] as int? ?? 0,
      behind: json['behind'] as int? ?? 0,
      modifiedFiles: (json['modified_files'] as List<dynamic>? ?? [])
          .map((e) => e as String)
          .toList(),
      observedAt: json['observed_at'] as String? ?? '',
    );
  }

  factory GitObserve.fromSnapshot(GitStatusSnapshot snapshot) {
    return GitObserve(
      branch: snapshot.branch,
      clean: !snapshot.dirty,
      repositoryHealth: snapshot.dirty ? 'Modified' : 'Clean',
      modifiedCount: snapshot.modifiedCount,
      ahead: snapshot.ahead,
      behind: snapshot.behind,
      modifiedFiles: snapshot.modifiedFiles,
      observedAt: snapshot.observedAt,
    );
  }

  final String branch;
  final bool clean;
  final String repositoryHealth;
  final int modifiedCount;
  final int ahead;
  final int behind;
  final List<String> modifiedFiles;
  final String observedAt;
}

class WorkstationProjection {
  WorkstationProjection({required this.cursorRunning});

  factory WorkstationProjection.fromJson(Map<String, dynamic> json) {
    return WorkstationProjection(
      cursorRunning: json['cursor_running'] as bool,
    );
  }

  final bool cursorRunning;
}

class ArchitectureProjection {
  ArchitectureProjection({required this.status});

  factory ArchitectureProjection.fromJson(Map<String, dynamic> json) {
    return ArchitectureProjection(status: json['status'] as String);
  }

  final String status;
}

class ReviewProjection {
  ReviewProjection({
    required this.title,
    required this.status,
    required this.date,
    required this.filename,
  });

  factory ReviewProjection.fromJson(Map<String, dynamic> json) {
    return ReviewProjection(
      title: json['title'] as String,
      status: json['status'] as String,
      date: json['date'] as String,
      filename: json['filename'] as String,
    );
  }

  final String title;
  final String status;
  final String date;
  final String filename;
}

class EngineeringDoc {
  EngineeringDoc({
    required this.slug,
    required this.path,
    required this.content,
  });

  factory EngineeringDoc.fromJson(Map<String, dynamic> json) {
    return EngineeringDoc(
      slug: json['slug'] as String,
      path: json['path'] as String,
      content: json['content'] as String,
    );
  }

  final String slug;
  final String path;
  final String content;
}
