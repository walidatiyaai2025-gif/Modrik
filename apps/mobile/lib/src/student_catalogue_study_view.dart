import 'package:flutter/material.dart';
import 'package:modrik_design_tokens/modrik_design_tokens.dart';

import 'copy.dart';
import 'learning_gateway.dart';
import 'mobile_learning_controller.dart';
import 'models.dart';
import 'student_content_catalogue.dart';

enum _MobileCatalogueState { loading, ready, empty, error, offline, permission }

class StudentCatalogueStudyView extends StatefulWidget {
  const StudentCatalogueStudyView({
    super.key,
    required this.controller,
    required this.copy,
  });

  final MobileLearningController controller;
  final MobileCopy copy;

  @override
  State<StudentCatalogueStudyView> createState() =>
      _StudentCatalogueStudyViewState();
}

class _StudentCatalogueStudyViewState
    extends State<StudentCatalogueStudyView> {
  _MobileCatalogueState _state = _MobileCatalogueState.loading;
  ContentCatalogue? _catalogue;
  String? _loadingLessonId;
  String? _loadingAssessmentId;

  MobileLearningController get controller => widget.controller;
  MobileCopy get copy => widget.copy;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_loadCatalogue);
  }

  @override
  void didUpdateWidget(covariant StudentCatalogueStudyView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.controller, widget.controller)) {
      _catalogue = null;
      _loadingLessonId = null;
      _loadingAssessmentId = null;
      Future<void>.microtask(_loadCatalogue);
    }
  }

  Future<void> _loadCatalogue() async {
    if (!mounted) return;
    if (controller.isOffline) {
      setState(() => _state = _MobileCatalogueState.offline);
      return;
    }

    final gateway = controller.gateway;
    if (gateway is! HttpLearningGateway) {
      // Isolated fixture/widget harnesses may still provide a deterministic
      // lesson. Production/Demo startup always uses HttpLearningGateway and
      // therefore cannot fall back to a fixture catalogue.
      setState(() {
        _state = controller.config.fixtureMode && controller.lesson != null
            ? _MobileCatalogueState.ready
            : _MobileCatalogueState.permission;
      });
      return;
    }

    setState(() => _state = _MobileCatalogueState.loading);
    try {
      final catalogue = await gateway.contentCatalogue();
      if (!mounted) return;
      setState(() {
        _catalogue = catalogue;
        _state = !catalogue.isActive || catalogue.subjects.isEmpty
            ? _MobileCatalogueState.empty
            : _MobileCatalogueState.ready;
      });
    } on LearningFailure catch (failure) {
      if (!mounted) return;
      setState(() {
        _state = failure.isPermission
            ? _MobileCatalogueState.permission
            : failure.isNetwork
                ? _MobileCatalogueState.offline
                : _MobileCatalogueState.error;
      });
    } catch (_) {
      if (mounted) setState(() => _state = _MobileCatalogueState.error);
    }
  }

  Future<void> _openLesson(CatalogueLesson item) async {
    if (controller.isOffline) {
      setState(() => _state = _MobileCatalogueState.offline);
      return;
    }
    final gateway = controller.gateway;
    if (gateway is! HttpLearningGateway) return;

    setState(() => _loadingLessonId = item.id);
    try {
      final lesson = await gateway.publishedLesson(item.id);
      controller.lesson = lesson;
      await controller.downloadedContentCache.writeLesson(
        lesson,
        DateTime.now(),
      );
      if (mounted) setState(() => _loadingLessonId = null);
    } on LearningFailure catch (failure) {
      if (!mounted) return;
      setState(() {
        _loadingLessonId = null;
        _state = failure.isPermission
            ? _MobileCatalogueState.permission
            : failure.isNetwork
                ? _MobileCatalogueState.offline
                : _MobileCatalogueState.error;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _loadingLessonId = null;
          _state = _MobileCatalogueState.error;
        });
      }
    }
  }

  Future<void> _startAssessment(CatalogueAssessment assessment) async {
    if (controller.isOffline || controller.isBusy) return;
    setState(() => _loadingAssessmentId = assessment.id);
    try {
      final started = await controller.gateway.startAttempt(
        assessment.id,
        newLogicalCommandKey(),
      );
      // resumeAttempt hydrates the existing controller answer/revision maps and
      // re-reads the backend-owned immutable attempt ordering before rendering.
      controller.attempt = started;
      await controller.resumeAttempt();
      if (mounted) setState(() => _loadingAssessmentId = null);
    } on LearningFailure catch (failure) {
      if (!mounted) return;
      setState(() {
        _loadingAssessmentId = null;
        _state = failure.isPermission
            ? _MobileCatalogueState.permission
            : failure.isNetwork
                ? _MobileCatalogueState.offline
                : _MobileCatalogueState.error;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _loadingAssessmentId = null;
          _state = _MobileCatalogueState.error;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_state == _MobileCatalogueState.loading) {
      return _stateView(
        context,
        icon: Icons.menu_book_outlined,
        title: copy.t('study'),
        body: copy.t('loading_body'),
        loading: true,
      );
    }
    if (_state == _MobileCatalogueState.permission) {
      return _stateView(
        context,
        icon: Icons.lock_outline,
        title: copy.t('permission_title'),
        body: copy.t('permission_body'),
        retry: true,
      );
    }
    if (_state == _MobileCatalogueState.error) {
      return _stateView(
        context,
        icon: Icons.cloud_off_outlined,
        title: copy.t('error_title'),
        body: copy.t('error_body'),
        retry: true,
      );
    }
    if (_state == _MobileCatalogueState.offline && !controller.hasLesson) {
      return _stateView(
        context,
        icon: Icons.cloud_off_outlined,
        title: copy.t('offline_title'),
        body: copy.t('offline_no_downloads'),
        retry: true,
      );
    }
    if (_state == _MobileCatalogueState.empty && !controller.hasLesson) {
      return _stateView(
        context,
        icon: Icons.inbox_outlined,
        title: copy.t('empty_title'),
        body: copy.t('empty_body'),
        retry: true,
      );
    }

    final catalogue = _catalogue;
    final lesson = controller.lesson;
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 760),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Semantics(
                header: true,
                child: Text(
                  copy.t('study'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: ModrikColors.navy,
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ),
              if (catalogue?.context case final catalogueContext?) ...[
                const SizedBox(height: 6),
                Text(
                  '${catalogueContext.yearLevel} · '
                  '${localize(catalogueContext.trackTitle, controller.locale)}',
                  style: const TextStyle(color: ModrikColors.slate),
                ),
              ],
              const SizedBox(height: 16),
              if (catalogue != null)
                for (final subject in catalogue.subjects) ...[
                  _CatalogueSurface(
                    child: _CatalogueNodeBranch(
                      node: subject,
                      controller: controller,
                      loadingLessonId: _loadingLessonId,
                      loadingAssessmentId: _loadingAssessmentId,
                      onLesson: _openLesson,
                      onAssessment: _startAssessment,
                    ),
                  ),
                  const SizedBox(height: 14),
                ],
              if (lesson != null) ...[
                _CatalogueSurface(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        localize(lesson.title, controller.locale),
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              color: ModrikColors.navy,
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                      const SizedBox(height: 14),
                      for (final block in lesson.blocks) ...[
                        Text(
                          localize(block.content, controller.locale),
                          style: Theme.of(context)
                              .textTheme
                              .bodyLarge
                              ?.copyWith(height: 1.65),
                        ),
                        const SizedBox(height: 14),
                      ],
                      if (lesson.practiceQuizId.isNotEmpty)
                        FilledButton.icon(
                          onPressed: controller.isOffline || controller.isBusy
                              ? null
                              : controller.startPractice,
                          icon: const Icon(Icons.play_arrow_rounded),
                          label: Text(copy.t('start_practice')),
                        ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _stateView(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String body,
    bool loading = false,
    bool retry = false,
  }) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 560),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (loading)
                const CircularProgressIndicator()
              else
                Icon(icon, size: 48, color: ModrikColors.blue),
              const SizedBox(height: 18),
              Text(
                title,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: ModrikColors.navy,
                    ),
              ),
              const SizedBox(height: 10),
              Text(body, textAlign: TextAlign.center),
              if (retry) ...[
                const SizedBox(height: 22),
                FilledButton.icon(
                  onPressed: _loadCatalogue,
                  icon: const Icon(Icons.refresh),
                  label: Text(copy.t('retry')),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _CatalogueNodeBranch extends StatelessWidget {
  const _CatalogueNodeBranch({
    required this.node,
    required this.controller,
    required this.loadingLessonId,
    required this.loadingAssessmentId,
    required this.onLesson,
    required this.onAssessment,
  });

  final CatalogueNode node;
  final MobileLearningController controller;
  final String? loadingLessonId;
  final String? loadingAssessmentId;
  final Future<void> Function(CatalogueLesson) onLesson;
  final Future<void> Function(CatalogueAssessment) onAssessment;

  @override
  Widget build(BuildContext context) {
    final title = localize(node.title, controller.locale);
    final hasChildren = node.children.isNotEmpty ||
        node.lessons.isNotEmpty ||
        node.assessments.isNotEmpty;
    if (!hasChildren) {
      return ListTile(
        contentPadding: EdgeInsets.zero,
        leading: Icon(_iconFor(node.type), color: ModrikColors.blue),
        title: Text(title),
      );
    }

    return ExpansionTile(
      initiallyExpanded: node.type == 'subject',
      tilePadding: EdgeInsets.zero,
      childrenPadding: const EdgeInsetsDirectional.only(start: 12),
      leading: Icon(_iconFor(node.type), color: ModrikColors.blue),
      title: Text(
        title,
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
      children: [
        for (final lesson in node.lessons)
          ListTile(
            contentPadding: const EdgeInsetsDirectional.only(start: 12),
            leading: const Icon(Icons.menu_book_outlined),
            title: Text(localize(lesson.title, controller.locale)),
            trailing: loadingLessonId == lesson.id
                ? const SizedBox.square(
                    dimension: 22,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.chevron_right),
            onTap: loadingLessonId == null && loadingAssessmentId == null
                ? () => onLesson(lesson)
                : null,
          ),
        for (final assessment in node.assessments)
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(12, 4, 0, 8),
            child: OutlinedButton.icon(
              onPressed: loadingLessonId == null &&
                      loadingAssessmentId == null &&
                      !controller.isOffline &&
                      !controller.isBusy
                  ? () => onAssessment(assessment)
                  : null,
              icon: loadingAssessmentId == assessment.id
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Icon(
                      assessment.kind == 'mock_exam'
                          ? Icons.fact_check_outlined
                          : Icons.quiz_outlined,
                    ),
              label: Text(localize(assessment.title, controller.locale)),
            ),
          ),
        for (final child in node.children)
          _CatalogueNodeBranch(
            node: child,
            controller: controller,
            loadingLessonId: loadingLessonId,
            loadingAssessmentId: loadingAssessmentId,
            onLesson: onLesson,
            onAssessment: onAssessment,
          ),
      ],
    );
  }

  IconData _iconFor(String type) => switch (type) {
        'subject' => Icons.library_books_outlined,
        'unit' => Icons.folder_outlined,
        'topic' => Icons.topic_outlined,
        _ => Icons.account_tree_outlined,
      };
}

class _CatalogueSurface extends StatelessWidget {
  const _CatalogueSurface({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: ModrikColors.white,
      elevation: 0,
      borderRadius: BorderRadius.circular(ModrikRadii.medium),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(ModrikRadii.medium),
          border: Border.all(color: ModrikColors.sky),
        ),
        child: child,
      ),
    );
  }
}
