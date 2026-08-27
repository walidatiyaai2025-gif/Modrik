import 'package:flutter/material.dart';
import 'package:modrik_design_tokens/modrik_design_tokens.dart';

import 'copy.dart';
import 'mobile_learning_controller.dart';
import 'models.dart';
import 'student_catalogue_study_view.dart';

class MobileStudentShell extends StatelessWidget {
  const MobileStudentShell({super.key, required this.controller});

  final MobileLearningController controller;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        final copy = MobileCopy(controller.locale);
        final textDirection = controller.locale == ModrikLocale.ar
            ? TextDirection.rtl
            : TextDirection.ltr;
        return Directionality(
          textDirection: textDirection,
          child: Scaffold(
            body: SafeArea(
              child: Column(
                children: [
                  _Header(controller: controller, copy: copy),
                  if (controller.status == MobileViewStatus.offline || controller.isStale)
                    _OfflineBanner(controller: controller, copy: copy),
                  if (controller.messageCode case final code?)
                    _MessageBanner(code: code, copy: copy),
                  Expanded(child: _body(copy)),
                ],
              ),
            ),
            bottomNavigationBar: _showNavigation
                ? _StudentNavigation(controller: controller, copy: copy)
                : null,
          ),
        );
      },
    );
  }

  bool get _showNavigation {
    return controller.status != MobileViewStatus.loading &&
        controller.status != MobileViewStatus.permission &&
        controller.status != MobileViewStatus.error &&
        !(controller.academicContext?.requiresOnboarding ?? false);
  }

  Widget _body(MobileCopy copy) {
    if (controller.status == MobileViewStatus.loading) {
      return _StateView(
        icon: Icons.school_outlined,
        title: copy.t('loading_title'),
        body: copy.t('loading_body'),
        loading: true,
      );
    }
    if (controller.status == MobileViewStatus.permission) {
      return _StateView(
        icon: Icons.lock_outline,
        title: copy.t('permission_title'),
        body: copy.t('permission_body'),
        actionLabel: copy.t('retry'),
        onAction: controller.refresh,
      );
    }
    if (controller.status == MobileViewStatus.error) {
      return _StateView(
        icon: Icons.cloud_off_outlined,
        title: copy.t('error_title'),
        body: copy.t('error_body'),
        actionLabel: copy.t('retry'),
        onAction: controller.refresh,
      );
    }
    if (controller.academicContext?.requiresOnboarding ?? false) {
      return _OnboardingView(controller: controller, copy: copy);
    }
    if (controller.status == MobileViewStatus.empty) {
      return _StateView(
        icon: Icons.inbox_outlined,
        title: copy.t('empty_title'),
        body: copy.t('empty_body'),
        actionLabel: copy.t('retry'),
        onAction: controller.refresh,
      );
    }
    return switch (controller.section) {
      StudentSection.dashboard => _DashboardView(controller: controller, copy: copy),
      StudentSection.study => StudentCatalogueStudyView(controller: controller, copy: copy),
      StudentSection.practice => _PracticeView(controller: controller, copy: copy),
      StudentSection.progress => _ProgressView(controller: controller, copy: copy),
    };
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.controller, required this.copy});

  final MobileLearningController controller;
  final MobileCopy copy;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Semantics(
              header: true,
              child: Text(
                copy.t('brand'),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: ModrikColors.navy,
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Semantics(
            label: copy.t('language'),
            child: PopupMenuButton<ModrikLocale>(
              tooltip: copy.t('language'),
              initialValue: controller.locale,
              onSelected: controller.setLocale,
              itemBuilder: (context) => const [
                PopupMenuItem(value: ModrikLocale.ar, child: Text('العربية')),
                PopupMenuItem(value: ModrikLocale.en, child: Text('English')),
                PopupMenuItem(value: ModrikLocale.fr, child: Text('Français')),
              ],
              child: ConstrainedBox(
                constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
                child: Center(
                  child: Text(
                    controller.locale.code.toUpperCase(),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OfflineBanner extends StatelessWidget {
  const _OfflineBanner({required this.controller, required this.copy});

  final MobileLearningController controller;
  final MobileCopy copy;

  @override
  Widget build(BuildContext context) {
    final detail = controller.status == MobileViewStatus.offline
        ? (controller.hasLesson || controller.hasAttempt
            ? copy.t('offline_cached')
            : copy.t('offline_no_downloads'))
        : copy.t('stale');
    return Semantics(
      liveRegion: true,
      child: Container(
        width: double.infinity,
        color: ModrikColors.sky,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.cloud_off_outlined, color: ModrikColors.blue),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                '${copy.t('offline_title')}: $detail',
                style: const TextStyle(color: ModrikColors.navy, height: 1.4),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MessageBanner extends StatelessWidget {
  const _MessageBanner({required this.code, required this.copy});

  final String code;
  final MobileCopy copy;

  @override
  Widget build(BuildContext context) {
    final message = copy.t(code);
    if (message == code) return const SizedBox.shrink();
    return Semantics(
      liveRegion: true,
      child: Container(
        width: double.infinity,
        margin: const EdgeInsets.fromLTRB(20, 4, 20, 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(ModrikRadii.small),
          border: Border.all(color: ModrikColors.warning),
        ),
        child: Text(message),
      ),
    );
  }
}

class _StateView extends StatelessWidget {
  const _StateView({
    required this.icon,
    required this.title,
    required this.body,
    this.loading = false,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String body;
  final bool loading;
  final String? actionLabel;
  final Future<void> Function()? onAction;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 560),
          child: Semantics(
            liveRegion: true,
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
                if (actionLabel != null && onAction != null) ...[
                  const SizedBox(height: 22),
                  FilledButton.icon(
                    onPressed: () => onAction!(),
                    icon: const Icon(Icons.refresh),
                    label: Text(actionLabel!),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _OnboardingView extends StatelessWidget {
  const _OnboardingView({required this.controller, required this.copy});

  final MobileLearningController controller;
  final MobileCopy copy;

  @override
  Widget build(BuildContext context) {
    final trackId = controller.config.academicTrackId;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 640),
          child: _SurfaceCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Semantics(
                  header: true,
                  child: Text(
                    copy.t('onboarding_title'),
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          color: ModrikColors.navy,
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(copy.t('onboarding_body')),
                const SizedBox(height: 20),
                _InfoRow(
                  icon: Icons.account_tree_outlined,
                  title: copy.t('assigned_track'),
                  value: trackId == null ? copy.t('track_missing') : _shortId(trackId),
                ),
                const SizedBox(height: 20),
                FilledButton.icon(
                  onPressed: trackId == null || controller.isBusy
                      ? null
                      : controller.activateConfiguredAcademicContext,
                  icon: controller.isBusy
                      ? const SizedBox.square(
                          dimension: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.verified_outlined),
                  label: Text(copy.t('confirm_context')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DashboardView extends StatelessWidget {
  const _DashboardView({required this.controller, required this.copy});

  final MobileLearningController controller;
  final MobileCopy copy;

  @override
  Widget build(BuildContext context) {
    final academic = controller.academicContext;
    return _ScrollablePage(
      title: copy.t('dashboard'),
      children: [
        _SurfaceCard(
          child: _InfoRow(
            icon: Icons.school_outlined,
            title: copy.t('academic_context'),
            value: academic?.state == 'active'
                ? '${copy.t('active')} · ${copy.t('year_level')}: ${academic?.yearLevel ?? '—'}'
                : '—',
          ),
        ),
        _SurfaceCard(
          child: _InfoRow(
            icon: Icons.download_done_outlined,
            title: copy.t('offline_learning'),
            value: controller.hasLesson ? copy.t('download_ready') : copy.t('no_download'),
          ),
        ),
        _SurfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _InfoRow(
                icon: Icons.sync_outlined,
                title: copy.t('pending_changes'),
                value: '${controller.pendingOperationCount} ${copy.t('pending_count')}',
              ),
              if (controller.pendingOperationCount > 0) ...[
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: controller.requestPendingSync,
                  icon: const Icon(Icons.sync),
                  label: Text(copy.t('sync_now')),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _PracticeView extends StatelessWidget {
  const _PracticeView({required this.controller, required this.copy});

  final MobileLearningController controller;
  final MobileCopy copy;

  @override
  Widget build(BuildContext context) {
    final attempt = controller.attempt;
    final canStartPractice = !controller.isOffline &&
        (controller.lesson?.practiceQuizId.isNotEmpty ?? false);
    if (attempt == null) {
      return _StateView(
        icon: Icons.quiz_outlined,
        title: copy.t('practice'),
        body: copy.t('attempt_empty'),
        actionLabel: canStartPractice ? copy.t('start_practice') : null,
        onAction: canStartPractice ? controller.startPractice : null,
      );
    }

    return _ScrollablePage(
      title: copy.t('practice'),
      subtitle: '${_shortId(attempt.id)} · ${attempt.status}',
      children: [
        OutlinedButton.icon(
          onPressed: controller.isBusy ? null : controller.resumeAttempt,
          icon: const Icon(Icons.restore),
          label: Text(copy.t('resume_practice')),
        ),
        // Iterate in the exact order returned by the backend attempt snapshot.
        for (var index = 0; index < attempt.questions.length; index++)
          _QuestionCard(
            index: index,
            question: attempt.questions[index],
            controller: controller,
            copy: copy,
          ),
        FilledButton.icon(
          onPressed: controller.isBusy ? null : controller.submitPractice,
          icon: controller.isBusy
              ? const SizedBox.square(
                  dimension: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.send_outlined),
          label: Text(copy.t('submit')),
        ),
        if (controller.result case final result?)
          _SurfaceCard(
            child: Semantics(
              liveRegion: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    copy.t('backend_result'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 8),
                  Text('${result.score.toStringAsFixed(1)} / ${result.maxScore.toStringAsFixed(1)}'),
                  const SizedBox(height: 6),
                  Text(copy.t('score_authority')),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({
    required this.index,
    required this.question,
    required this.controller,
    required this.copy,
  });

  final int index;
  final AttemptQuestion question;
  final MobileLearningController controller;
  final MobileCopy copy;

  @override
  Widget build(BuildContext context) {
    final selected = controller.answers[question.attemptQuestionId];
    final selectedIds = selected is List
        ? selected.whereType<String>().toSet()
        : const <String>{};
    final textValue = selected is String
        ? selected
        : selected is num
            ? selected.toString()
            : '';
    return _SurfaceCard(
      child: Semantics(
        container: true,
        label: '${copy.t('question')} ${index + 1}',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              '${copy.t('question')} ${index + 1}',
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    color: ModrikColors.blue,
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 8),
            Text(
              localize(question.prompt, controller.locale),
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    height: 1.45,
                  ),
            ),
            const SizedBox(height: 14),
            if (question.responseContract.kind == 'single_choice')
              for (final option in question.responseContract.options)
                _ChoiceTile(
                  label: localize(option.label, controller.locale),
                  selected: selected == option.id,
                  onPressed: () => controller.setAnswer(
                    question.attemptQuestionId,
                    option.id,
                  ),
                )
            else if (question.responseContract.kind == 'multiple_choice')
              for (final option in question.responseContract.options)
                _ChoiceTile(
                  label: localize(option.label, controller.locale),
                  selected: selectedIds.contains(option.id),
                  multiple: true,
                  onPressed: () {
                    final next = <String>{...selectedIds};
                    if (!next.add(option.id)) {
                      next.remove(option.id);
                    }
                    controller.setAnswer(
                      question.attemptQuestionId,
                      [
                        for (final candidate in question.responseContract.options)
                          if (next.contains(candidate.id)) candidate.id,
                      ],
                    );
                  },
                )
            else
              TextFormField(
                key: ValueKey('${question.attemptQuestionId}-answer'),
                initialValue: textValue,
                maxLength: question.responseContract.maxLength,
                minLines: 2,
                maxLines: 5,
                keyboardType: question.type == 'numeric'
                    ? const TextInputType.numberWithOptions(decimal: true, signed: true)
                    : TextInputType.text,
                decoration: InputDecoration(
                  labelText: copy.t('answer_hint'),
                  border: const OutlineInputBorder(),
                ),
                onChanged: (value) => controller.setAnswer(
                  question.attemptQuestionId,
                  question.type == 'numeric' ? (num.tryParse(value) ?? value) : value,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _ChoiceTile extends StatelessWidget {
  const _ChoiceTile({
    required this.label,
    required this.selected,
    required this.onPressed,
    this.multiple = false,
  });

  final String label;
  final bool selected;
  final VoidCallback onPressed;
  final bool multiple;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Semantics(
        button: true,
        selected: selected,
        label: label,
        child: InkWell(
          borderRadius: BorderRadius.circular(ModrikRadii.small),
          onTap: onPressed,
          child: Container(
            constraints: const BoxConstraints(minHeight: 52),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(ModrikRadii.small),
              border: Border.all(
                color: selected ? ModrikColors.teal : ModrikColors.slate,
                width: selected ? 2 : 1,
              ),
            ),
            child: Row(
              children: [
                Icon(
                  multiple
                      ? (selected ? Icons.check_box : Icons.check_box_outline_blank)
                      : (selected ? Icons.radio_button_checked : Icons.radio_button_unchecked),
                  color: selected ? ModrikColors.teal : ModrikColors.slate,
                ),
                const SizedBox(width: 12),
                Expanded(child: Text(label)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ProgressView extends StatelessWidget {
  const _ProgressView({required this.controller, required this.copy});

  final MobileLearningController controller;
  final MobileCopy copy;

  @override
  Widget build(BuildContext context) {
    if (controller.progress.isEmpty) {
      return _StateView(
        icon: Icons.insights_outlined,
        title: copy.t('progress'),
        body: copy.t('progress_empty'),
      );
    }
    return _ScrollablePage(
      title: copy.t('progress'),
      children: [
        for (final snapshot in controller.progress)
          _SurfaceCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '${copy.t('mastery')} · ${_shortId(snapshot.curriculumNodeId)}',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 12),
                Semantics(
                  label: '${copy.t('mastery')} ${(snapshot.mastery * 100).round()}%',
                  child: LinearProgressIndicator(
                    value: snapshot.mastery.clamp(0.0, 1.0).toDouble(),
                    minHeight: 10,
                    borderRadius: BorderRadius.circular(ModrikRadii.pill),
                  ),
                ),
                const SizedBox(height: 8),
                Text('${(snapshot.mastery * 100).round()}%'),
              ],
            ),
          ),
      ],
    );
  }
}

class _StudentNavigation extends StatelessWidget {
  const _StudentNavigation({required this.controller, required this.copy});

  final MobileLearningController controller;
  final MobileCopy copy;

  @override
  Widget build(BuildContext context) {
    return NavigationBar(
      selectedIndex: controller.section.index,
      onDestinationSelected: (index) => controller.setSection(StudentSection.values[index]),
      destinations: [
        NavigationDestination(icon: const Icon(Icons.home_outlined), label: copy.t('dashboard')),
        NavigationDestination(icon: const Icon(Icons.menu_book_outlined), label: copy.t('study')),
        NavigationDestination(icon: const Icon(Icons.quiz_outlined), label: copy.t('practice')),
        NavigationDestination(icon: const Icon(Icons.insights_outlined), label: copy.t('progress')),
      ],
    );
  }
}

class _ScrollablePage extends StatelessWidget {
  const _ScrollablePage({required this.title, required this.children, this.subtitle});

  final String title;
  final String? subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
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
                  title,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: ModrikColors.navy,
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 6),
                Text(subtitle!, style: const TextStyle(color: ModrikColors.slate)),
              ],
              const SizedBox(height: 16),
              for (final child in children) ...[
                child,
                const SizedBox(height: 14),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _SurfaceCard extends StatelessWidget {
  const _SurfaceCard({required this.child});

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

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.title, required this.value});

  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: ModrikColors.blue),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              Text(value, style: const TextStyle(color: ModrikColors.slate, height: 1.4)),
            ],
          ),
        ),
      ],
    );
  }
}

String _shortId(String value) {
  if (value.length <= 10) return value;
  return '…${value.substring(value.length - 8)}';
}
