import 'dart:async';

import 'package:flutter/material.dart';

import 'academic_track_catalogue.dart';
import 'learning_gateway.dart';
import 'mobile_learning_controller.dart';
import 'models.dart';

enum _CatalogueState { loading, ready, empty, error, offline, permission }

class AcademicContextResetBoundary extends StatefulWidget {
  const AcademicContextResetBoundary({
    super.key,
    required this.controller,
    required this.child,
  });

  final MobileLearningController controller;
  final Widget child;

  @override
  State<AcademicContextResetBoundary> createState() =>
      _AcademicContextResetBoundaryState();
}

class _AcademicContextResetBoundaryState
    extends State<AcademicContextResetBoundary> {
  _CatalogueState _state = _CatalogueState.loading;
  List<AcademicTrack> _tracks = const [];
  String? _selectedYearKey;
  String? _selectedTrackId;
  String? _operationKey;
  String? _transitionMessage;
  bool _transitionBusy = false;

  MobileLearningController get controller => widget.controller;
  _CatalogueCopy get copy => _CatalogueCopy(controller.locale);

  @override
  void initState() {
    super.initState();
    controller.addListener(_handleControllerChanged);
    scheduleMicrotask(_loadCatalogue);
  }

  @override
  void didUpdateWidget(covariant AcademicContextResetBoundary oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.controller, widget.controller)) {
      oldWidget.controller.removeListener(_handleControllerChanged);
      controller.addListener(_handleControllerChanged);
      _operationKey = null;
      _transitionMessage = null;
      scheduleMicrotask(_loadCatalogue);
    }
  }

  @override
  void dispose() {
    controller.removeListener(_handleControllerChanged);
    super.dispose();
  }

  void _handleControllerChanged() {
    if (mounted) setState(() {});
  }

  List<AcademicYear> get _years {
    final years = <AcademicYear>[];
    final seen = <String>{};
    for (final track in _tracks) {
      final year = track.year;
      if (year != null && seen.add(year.key)) years.add(year);
    }
    return years;
  }

  List<AcademicTrack> _tracksForYear(String? yearKey) {
    if (yearKey == null) return _tracks;
    return _tracks
        .where((track) => track.year?.key == yearKey)
        .toList(growable: false);
  }

  Future<void> _loadCatalogue() async {
    if (!mounted) return;
    if (controller.isOffline) {
      setState(() => _state = _CatalogueState.offline);
      return;
    }
    final gateway = controller.gateway;
    if (gateway is! AcademicTrackCatalogueGateway) {
      setState(() => _state = _CatalogueState.permission);
      return;
    }
    final catalogueGateway = gateway as AcademicTrackCatalogueGateway;

    setState(() => _state = _CatalogueState.loading);
    try {
      final tracks = await catalogueGateway.academicTracks();
      if (!mounted) return;
      final currentTrackId = controller.academicContext?.academicTrackId;
      final currentTrack = tracks
          .where((track) => track.id == currentTrackId)
          .firstOrNull;
      final firstYear = tracks
          .map((track) => track.year)
          .whereType<AcademicYear>()
          .firstOrNull;
      final selectedYearKey = currentTrack?.year?.key ?? firstYear?.key;
      final tracksInYear = selectedYearKey == null
          ? tracks
          : tracks
              .where((track) => track.year?.key == selectedYearKey)
              .toList(growable: false);
      final selectedTrackId =
          tracksInYear.any((track) => track.id == currentTrackId)
              ? currentTrackId
              : tracksInYear.firstOrNull?.id;
      setState(() {
        _tracks = tracks;
        _selectedYearKey = selectedYearKey;
        _selectedTrackId = selectedTrackId;
        _state =
            tracks.isEmpty ? _CatalogueState.empty : _CatalogueState.ready;
      });
    } on LearningFailure catch (failure) {
      if (!mounted) return;
      setState(() {
        _state = failure.isPermission
            ? _CatalogueState.permission
            : failure.isNetwork
                ? _CatalogueState.offline
                : _CatalogueState.error;
      });
    } catch (_) {
      if (mounted) setState(() => _state = _CatalogueState.error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final body = controller.academicContext?.requiresOnboarding ?? false
        ? _buildOnboarding(context)
        : _buildActiveContext(context);
    return Directionality(
      textDirection: controller.locale == ModrikLocale.ar
          ? TextDirection.rtl
          : TextDirection.ltr,
      child: body,
    );
  }

  Widget _buildActiveContext(BuildContext context) {
    final currentTrack = controller.academicContext?.academicTrackId;
    final hasAlternative = _state == _CatalogueState.ready &&
        _tracks.any((track) => track.id != currentTrack);
    if (_state == _CatalogueState.ready && !hasAlternative) {
      return widget.child;
    }

    final lane = hasAlternative
        ? Semantics(
            button: true,
            label: copy.change,
            child: FilledButton.icon(
              onPressed: controller.isBusy || _transitionBusy
                  ? null
                  : () => _showResetDialog(context),
              icon: const Icon(Icons.swap_horiz_outlined),
              label: Text(copy.change, textAlign: TextAlign.center),
            ),
          )
        : _activeCatalogueStateCard();
    final laneContainer = SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 560),
            child: lane,
          ),
        ),
      ),
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        final largeText = MediaQuery.textScalerOf(context).scale(16) >= 24;
        final constrainStateLane =
            !hasAlternative && constraints.maxWidth < 360 && largeText;
        if (!constrainStateLane) {
          return Column(
            children: [
              Expanded(child: widget.child),
              laneContainer,
            ],
          );
        }

        return Column(
          children: [
            Expanded(child: widget.child),
            Flexible(
              fit: FlexFit.loose,
              child: SingleChildScrollView(child: laneContainer),
            ),
          ],
        );
      },
    );
  }

  Widget _activeCatalogueStateCard() {
    final (icon, message, canRetry, loading) = switch (_state) {
      _CatalogueState.loading =>
        (Icons.sync_outlined, copy.loading, false, true),
      _CatalogueState.empty =>
        (Icons.inbox_outlined, copy.empty, true, false),
      _CatalogueState.offline =>
        (Icons.cloud_off_outlined, copy.offline, true, false),
      _CatalogueState.permission =>
        (Icons.lock_outline, copy.permission, true, false),
      _CatalogueState.error =>
        (Icons.error_outline, copy.error, true, false),
      _CatalogueState.ready =>
        (Icons.check_circle_outline, copy.empty, false, false),
    };
    final leading = loading
        ? const SizedBox.square(
            dimension: 24,
            child: CircularProgressIndicator(strokeWidth: 3),
          )
        : Icon(icon, size: 28);
    final retryButton = canRetry
        ? OutlinedButton.icon(
            onPressed: _loadCatalogue,
            icon: const Icon(Icons.refresh),
            label: Text(copy.retry),
          )
        : null;

    return Card(
      key: ValueKey('academic-catalogue-active-state-${_state.name}'),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Semantics(
          container: true,
          liveRegion: true,
          child: LayoutBuilder(
            builder: (context, constraints) {
              final largeText =
                  MediaQuery.textScalerOf(context).scale(16) >= 24;
              final stackContent = constraints.maxWidth < 360 || largeText;
              if (stackContent) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        leading,
                        const SizedBox(width: 12),
                        Expanded(child: Text(message)),
                      ],
                    ),
                    if (retryButton != null) ...[
                      const SizedBox(height: 12),
                      Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: retryButton,
                      ),
                    ],
                  ],
                );
              }

              return Row(
                children: [
                  leading,
                  const SizedBox(width: 12),
                  Expanded(child: Text(message)),
                  if (retryButton != null) ...[
                    const SizedBox(width: 8),
                    retryButton,
                  ],
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildOnboarding(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 640),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Semantics(
                        header: true,
                        child: Text(
                          copy.onboardingTitle,
                          style: Theme.of(context)
                              .textTheme
                              .headlineSmall
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(copy.onboardingBody),
                      const SizedBox(height: 20),
                      _onboardingCatalogueBody(context),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _yearSelector({required String keyPrefix}) {
    final years = _years;
    if (years.isEmpty) return const SizedBox.shrink();
    final selectedYear = years.any((year) => year.key == _selectedYearKey)
        ? _selectedYearKey
        : years.first.key;
    return DropdownButtonFormField<String>(
      key: ValueKey('$keyPrefix-${controller.locale.name}'),
      initialValue: selectedYear,
      isExpanded: true,
      decoration: InputDecoration(labelText: copy.yearLabel),
      items: [
        for (final year in years)
          DropdownMenuItem<String>(
            value: year.key,
            child: Text(year.label, maxLines: 2),
          ),
      ],
      onChanged: _transitionBusy
          ? null
          : (value) {
              if (value == null) return;
              final tracks = _tracksForYear(value);
              setState(() {
                _selectedYearKey = value;
                _selectedTrackId = tracks.firstOrNull?.id;
                _operationKey = null;
                _transitionMessage = null;
              });
            },
    );
  }

  Widget _onboardingCatalogueBody(BuildContext context) {
    switch (_state) {
      case _CatalogueState.loading:
        return Semantics(
          liveRegion: true,
          child: Column(
            children: [
              const CircularProgressIndicator(),
              const SizedBox(height: 12),
              Text(copy.loading, textAlign: TextAlign.center),
            ],
          ),
        );
      case _CatalogueState.empty:
        return _StateMessage(
          icon: Icons.inbox_outlined,
          message: copy.empty,
          actionLabel: copy.retry,
          onAction: _loadCatalogue,
        );
      case _CatalogueState.offline:
        return _StateMessage(
          icon: Icons.cloud_off_outlined,
          message: copy.offline,
          actionLabel: copy.retry,
          onAction: _loadCatalogue,
        );
      case _CatalogueState.permission:
        return _StateMessage(
          icon: Icons.lock_outline,
          message: copy.permission,
          actionLabel: copy.retry,
          onAction: _loadCatalogue,
        );
      case _CatalogueState.error:
        return _StateMessage(
          icon: Icons.error_outline,
          message: copy.error,
          actionLabel: copy.retry,
          onAction: _loadCatalogue,
        );
      case _CatalogueState.ready:
        final visibleTracks = _tracksForYear(_selectedYearKey);
        final effectiveSelection =
            visibleTracks.any((track) => track.id == _selectedTrackId)
                ? _selectedTrackId
                : visibleTracks.firstOrNull?.id;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _yearSelector(keyPrefix: 'academic-year-onboarding'),
            if (_years.isNotEmpty) const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              key: ValueKey(
                'academic-track-onboarding-${controller.locale.name}-${_selectedYearKey ?? 'legacy'}',
              ),
              initialValue: effectiveSelection,
              isExpanded: true,
              decoration: InputDecoration(labelText: copy.trackLabel),
              items: [
                for (final track in visibleTracks)
                  DropdownMenuItem<String>(
                    value: track.id,
                    child: Text(track.label(controller.locale), maxLines: 2),
                  ),
              ],
              onChanged: _transitionBusy
                  ? null
                  : (value) {
                      setState(() {
                        _selectedTrackId = value;
                        _operationKey = null;
                        _transitionMessage = null;
                      });
                    },
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed:
                  _transitionBusy || controller.isBusy || effectiveSelection == null
                      ? null
                      : _activateSelection,
              child: _transitionBusy
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(copy.activate),
            ),
            if (_transitionMessage != null) ...[
              const SizedBox(height: 12),
              Text(
                _transitionMessage!,
                key: const ValueKey('academic-transition-error'),
                textAlign: TextAlign.center,
              ),
            ],
          ],
        );
    }
  }

  Future<void> _activateSelection() async {
    final selected = _selectedTrackId;
    if (selected == null) return;
    setState(() {
      _transitionBusy = true;
      _transitionMessage = null;
    });
    final key = _operationKey ??= newLogicalCommandKey();
    await controller.activateAcademicContext(selected, idempotencyKey: key);
    if (!mounted) return;
    final succeeded = controller.academicContext?.academicTrackId == selected &&
        controller.academicContext?.state == 'active';
    setState(() {
      _transitionBusy = false;
      if (succeeded) {
        _operationKey = null;
      } else {
        _transitionMessage = copy.transitionFailed;
      }
    });
    if (succeeded) await _loadCatalogue();
  }

  Future<void> _showResetDialog(BuildContext context) async {
    final currentTrackId = controller.academicContext?.academicTrackId;
    final alternatives = _tracks
        .where((track) => track.id != currentTrackId)
        .toList(growable: false);
    if (alternatives.isEmpty) return;

    final changed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => _ResetDialog(
        controller: controller,
        tracks: alternatives,
      ),
    );
    if (changed == true && mounted) {
      await _loadCatalogue();
    }
  }
}

class _ResetDialog extends StatefulWidget {
  const _ResetDialog({
    required this.controller,
    required this.tracks,
  });

  final MobileLearningController controller;
  final List<AcademicTrack> tracks;

  @override
  State<_ResetDialog> createState() => _ResetDialogState();
}

class _ResetDialogState extends State<_ResetDialog> {
  late String _selectedTrackId;
  String? _selectedYearKey;
  String? _operationKey;
  bool _confirmed = false;
  bool _busy = false;
  String? _message;

  List<AcademicYear> get _years {
    final years = <AcademicYear>[];
    final seen = <String>{};
    for (final track in widget.tracks) {
      final year = track.year;
      if (year != null && seen.add(year.key)) years.add(year);
    }
    return years;
  }

  List<AcademicTrack> _tracksForYear(String? yearKey) {
    if (yearKey == null) return widget.tracks;
    return widget.tracks
        .where((track) => track.year?.key == yearKey)
        .toList(growable: false);
  }

  @override
  void initState() {
    super.initState();
    _selectedYearKey = widget.tracks.first.year?.key;
    _selectedTrackId = widget.tracks.first.id;
    widget.controller.addListener(_handleControllerChanged);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_handleControllerChanged);
    super.dispose();
  }

  void _handleControllerChanged() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final copy = _CatalogueCopy(widget.controller.locale);
    final locale = widget.controller.locale;
    final years = _years;
    final visibleTracks = _tracksForYear(_selectedYearKey);
    if (!visibleTracks.any((track) => track.id == _selectedTrackId) &&
        visibleTracks.isNotEmpty) {
      _selectedTrackId = visibleTracks.first.id;
    }
    return Directionality(
      textDirection:
          locale == ModrikLocale.ar ? TextDirection.rtl : TextDirection.ltr,
      child: AlertDialog(
        scrollable: true,
        title: Text(copy.resetTitle),
        content: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            if (years.isNotEmpty) ...[
              DropdownButtonFormField<String>(
                key: ValueKey('academic-year-reset-${locale.name}'),
                initialValue: _selectedYearKey,
                isExpanded: true,
                decoration: InputDecoration(labelText: copy.yearLabel),
                items: [
                  for (final year in years)
                    DropdownMenuItem<String>(
                      value: year.key,
                      child: Text(year.label, maxLines: 2),
                    ),
                ],
                onChanged: _busy
                    ? null
                    : (value) {
                        if (value == null) return;
                        final tracks = _tracksForYear(value);
                        setState(() {
                          _selectedYearKey = value;
                          if (tracks.isNotEmpty) {
                            _selectedTrackId = tracks.first.id;
                          }
                          _operationKey = null;
                          _confirmed = false;
                          _message = null;
                        });
                      },
              ),
              const SizedBox(height: 16),
            ],
            DropdownButtonFormField<String>(
              key: ValueKey(
                'academic-track-reset-${locale.name}-${_selectedYearKey ?? 'legacy'}',
              ),
              initialValue: visibleTracks.isEmpty ? null : _selectedTrackId,
              isExpanded: true,
              decoration: InputDecoration(labelText: copy.trackLabel),
              items: [
                for (final track in visibleTracks)
                  DropdownMenuItem<String>(
                    value: track.id,
                    child: Text(track.label(locale), maxLines: 2),
                  ),
              ],
              onChanged: _busy
                  ? null
                  : (value) {
                      if (value == null) return;
                      setState(() {
                        _selectedTrackId = value;
                        _operationKey = null;
                        _confirmed = false;
                        _message = null;
                      });
                    },
            ),
            const SizedBox(height: 16),
            Text(copy.resetBody),
            const SizedBox(height: 8),
            Text(copy.syncWarning),
            const SizedBox(height: 12),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _confirmed,
              onChanged: _busy
                  ? null
                  : (value) => setState(() => _confirmed = value ?? false),
              title: Text(copy.confirmConsequences),
              controlAffinity: ListTileControlAffinity.leading,
            ),
            if (_message != null)
              Text(
                _message!,
                key: const ValueKey('academic-reset-error'),
              ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: _busy ? null : () => Navigator.of(context).pop(false),
            child: Text(copy.cancel),
          ),
          FilledButton(
            onPressed: _busy || !_confirmed || visibleTracks.isEmpty
                ? null
                : _applyReset,
            child: _busy
                ? const SizedBox.square(
                    dimension: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(copy.confirm),
          ),
        ],
      ),
    );
  }

  Future<void> _applyReset() async {
    setState(() {
      _busy = true;
      _message = null;
    });
    final key = _operationKey ??= newLogicalCommandKey();
    await widget.controller.resetAcademicContext(
      _selectedTrackId,
      idempotencyKey: key,
    );
    if (!mounted) return;
    final succeeded = widget.controller.academicContext?.academicTrackId ==
            _selectedTrackId &&
        widget.controller.academicContext?.state == 'active';
    if (succeeded) {
      Navigator.of(context).pop(true);
      return;
    }
    setState(() {
      _busy = false;
      _message = _CatalogueCopy(widget.controller.locale).transitionFailed;
    });
  }
}

class _StateMessage extends StatelessWidget {
  const _StateMessage({
    required this.icon,
    required this.message,
    required this.actionLabel,
    required this.onAction,
  });

  final IconData icon;
  final String message;
  final String actionLabel;
  final Future<void> Function() onAction;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      child: Column(
        children: [
          Icon(icon, size: 40),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: onAction,
            icon: const Icon(Icons.refresh),
            label: Text(actionLabel),
          ),
        ],
      ),
    );
  }
}

class _CatalogueCopy {
  const _CatalogueCopy(this.locale);

  final ModrikLocale locale;

  String get onboardingTitle => switch (locale) {
        ModrikLocale.ar => 'اختر مسارك الأكاديمي',
        ModrikLocale.en => 'Choose your academic track',
        ModrikLocale.fr => 'Choisissez votre parcours académique',
      };
  String get onboardingBody => switch (locale) {
        ModrikLocale.ar => 'هذه هي المسارات الأكاديمية المتاحة لك حاليًا.',
        ModrikLocale.en =>
          'These are the academic tracks currently available to you.',
        ModrikLocale.fr =>
          'Voici les parcours académiques actuellement disponibles pour vous.',
      };
  String get yearLabel => switch (locale) {
        ModrikLocale.ar => 'السنة الدراسية',
        ModrikLocale.en => 'School year',
        ModrikLocale.fr => 'Année scolaire',
      };
  String get trackLabel => switch (locale) {
        ModrikLocale.ar => 'المسار الأكاديمي',
        ModrikLocale.en => 'Academic track',
        ModrikLocale.fr => 'Parcours académique',
      };
  String get activate => switch (locale) {
        ModrikLocale.ar => 'ابدأ بهذا المسار',
        ModrikLocale.en => 'Start with this track',
        ModrikLocale.fr => 'Commencer avec ce parcours',
      };
  String get change => switch (locale) {
        ModrikLocale.ar => 'تغيير المسار الأكاديمي',
        ModrikLocale.en => 'Change academic track',
        ModrikLocale.fr => 'Changer de parcours académique',
      };
  String get resetTitle => switch (locale) {
        ModrikLocale.ar => 'قبل تغيير المسار الأكاديمي',
        ModrikLocale.en => 'Before you change academic track',
        ModrikLocale.fr => 'Avant de changer de parcours académique',
      };
  String get resetBody => switch (locale) {
        ModrikLocale.ar =>
          'سيتم أرشفة مسارك الأكاديمي السابق ومحاولاتك وتقدّمك، ولن يتم حذفها. وقد يبقى أي عمل جارٍ غير مكتمل.',
        ModrikLocale.en =>
          'Your previous academic track, attempts, and progress will be archived—not deleted. Any work still in progress may be left unfinished.',
        ModrikLocale.fr =>
          'Votre ancien parcours académique, vos tentatives et votre progression seront archivés, pas supprimés. Tout travail en cours pourra rester inachevé.',
      };
  String get syncWarning => switch (locale) {
        ModrikLocale.ar =>
          'زامن جميع الإجابات والتغييرات المعلّقة قبل المتابعة.',
        ModrikLocale.en =>
          'Sync all pending answers and changes before you continue.',
        ModrikLocale.fr =>
          'Synchronisez toutes les réponses et modifications en attente avant de continuer.',
      };
  String get confirmConsequences => switch (locale) {
        ModrikLocale.ar => 'أفهم ما سيحدث عند تغيير المسار.',
        ModrikLocale.en =>
          'I understand what will happen when I change tracks.',
        ModrikLocale.fr =>
          'Je comprends ce qui se passera quand je changerai de parcours.',
      };
  String get loading => switch (locale) {
        ModrikLocale.ar => 'جارٍ تحميل مساراتك الأكاديمية.',
        ModrikLocale.en => 'Loading your academic tracks.',
        ModrikLocale.fr => 'Chargement de vos parcours académiques.',
      };
  String get empty => switch (locale) {
        ModrikLocale.ar => 'لا توجد مسارات أكاديمية متاحة لك حاليًا.',
        ModrikLocale.en =>
          'No academic tracks are available to you right now.',
        ModrikLocale.fr =>
          'Aucun parcours académique n’est disponible pour vous pour le moment.',
      };
  String get offline => switch (locale) {
        ModrikLocale.ar =>
          'اتصل بالإنترنت لتحميل مساراتك الأكاديمية أو تغيير مسارك.',
        ModrikLocale.en =>
          'Reconnect to load your academic tracks or change your track.',
        ModrikLocale.fr =>
          'Reconnectez-vous pour charger vos parcours académiques ou changer de parcours.',
      };
  String get permission => switch (locale) {
        ModrikLocale.ar =>
          'لا يمكن تحميل المسارات الأكاديمية بهذه الجلسة. سجّل الدخول من جديد ثم حاول مرة أخرى.',
        ModrikLocale.en =>
          'We can’t load academic tracks with this session. Sign in again, then try again.',
        ModrikLocale.fr =>
          'Cette session ne permet pas de charger vos parcours académiques. Reconnectez-vous à votre compte, puis réessayez.',
      };
  String get error => switch (locale) {
        ModrikLocale.ar =>
          'تعذر تحميل مساراتك الأكاديمية. لم يتغير شيء. حاول مرة أخرى.',
        ModrikLocale.en =>
          'We couldn’t load your academic tracks. Nothing has changed. Try again.',
        ModrikLocale.fr =>
          'Nous n’avons pas pu charger vos parcours académiques. Rien n’a changé. Réessayez.',
      };
  String get transitionFailed => switch (locale) {
        ModrikLocale.ar =>
          'تعذر تحديث مسارك الأكاديمي. لم يتغير شيء. تحقق من اتصالك ومن أن المسار المختار ما زال متاحًا، ثم حاول مرة أخرى.',
        ModrikLocale.en =>
          'We couldn’t update your academic track. Nothing changed. Check your connection and that the selected track is still available, then try again.',
        ModrikLocale.fr =>
          'Nous n’avons pas pu mettre à jour votre parcours académique. Rien n’a changé. Vérifiez votre connexion et que le parcours choisi est toujours disponible, puis réessayez.',
      };
  String get retry => switch (locale) {
        ModrikLocale.ar => 'حاول مرة أخرى',
        ModrikLocale.en => 'Try again',
        ModrikLocale.fr => 'Réessayer',
      };
  String get cancel => switch (locale) {
        ModrikLocale.ar => 'إلغاء',
        ModrikLocale.en => 'Cancel',
        ModrikLocale.fr => 'Annuler',
      };
  String get confirm => switch (locale) {
        ModrikLocale.ar => 'تغيير المسار الأكاديمي',
        ModrikLocale.en => 'Change academic track',
        ModrikLocale.fr => 'Changer de parcours académique',
      };
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
