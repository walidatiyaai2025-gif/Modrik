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
      final selected = tracks.any((track) => track.id == currentTrackId)
          ? currentTrackId
          : tracks.firstOrNull?.id;
      setState(() {
        _tracks = tracks;
        _selectedTrackId = selected;
        _state = tracks.isEmpty ? _CatalogueState.empty : _CatalogueState.ready;
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

    return Stack(
      children: [
        widget.child,
        PositionedDirectional(
          start: 16,
          end: 16,
          bottom: 92,
          child: SafeArea(
            top: false,
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 560),
                child: hasAlternative
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
                    : _activeCatalogueStateCard(),
              ),
            ),
          ),
        ),
      ],
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
    return Card(
      key: ValueKey('academic-catalogue-active-state-${_state.name}'),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Semantics(
          container: true,
          liveRegion: true,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (loading)
                const SizedBox.square(
                  dimension: 28,
                  child: CircularProgressIndicator(strokeWidth: 3),
                )
              else
                Icon(icon, size: 32),
              const SizedBox(height: 8),
              Text(message, textAlign: TextAlign.center),
              if (canRetry) ...[
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: _loadCatalogue,
                  icon: const Icon(Icons.refresh),
                  label: Text(copy.retry),
                ),
              ],
            ],
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
        final effectiveSelection = _selectedTrackId ?? _tracks.first.id;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<String>(
              key: ValueKey('academic-track-onboarding-${controller.locale.name}'),
              initialValue: effectiveSelection,
              decoration: InputDecoration(labelText: copy.trackLabel),
              items: [
                for (final track in _tracks)
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
              onPressed: _transitionBusy || controller.isBusy
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
  String? _operationKey;
  bool _confirmed = false;
  bool _busy = false;
  String? _message;

  @override
  void initState() {
    super.initState();
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
            DropdownButtonFormField<String>(
              key: ValueKey('academic-track-reset-${locale.name}'),
              initialValue: _selectedTrackId,
              decoration: InputDecoration(labelText: copy.trackLabel),
              items: [
                for (final track in widget.tracks)
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
            onPressed: _busy || !_confirmed ? null : _applyReset,
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
        ModrikLocale.ar => 'اختر سياقك الأكاديمي',
        ModrikLocale.en => 'Choose your academic context',
        ModrikLocale.fr => 'Choisissez votre contexte académique',
      };
  String get onboardingBody => switch (locale) {
        ModrikLocale.ar => 'تظهر فقط المسارات التي صرّح بها الخادم لهذه الجلسة.',
        ModrikLocale.en =>
          'Only tracks authorized by the backend for this learner are shown.',
        ModrikLocale.fr =>
          'Seuls les parcours autorisés par le serveur pour cet élève sont affichés.',
      };
  String get trackLabel => switch (locale) {
        ModrikLocale.ar => 'المسار الأكاديمي',
        ModrikLocale.en => 'Academic track',
        ModrikLocale.fr => 'Parcours académique',
      };
  String get activate => switch (locale) {
        ModrikLocale.ar => 'تفعيل السياق',
        ModrikLocale.en => 'Activate context',
        ModrikLocale.fr => 'Activer le contexte',
      };
  String get change => switch (locale) {
        ModrikLocale.ar => 'تغيير المسار الأكاديمي',
        ModrikLocale.en => 'Change academic track',
        ModrikLocale.fr => 'Changer de parcours académique',
      };
  String get resetTitle => switch (locale) {
        ModrikLocale.ar => 'تأكيد تغيير المسار',
        ModrikLocale.en => 'Confirm academic-track change',
        ModrikLocale.fr => 'Confirmer le changement de parcours',
      };
  String get resetBody => switch (locale) {
        ModrikLocale.ar =>
          'سيؤرشف الخادم السياق السابق والمحاولات والتقدّم بدل حذفها، وقد تُنهى المحاولة الجارية.',
        ModrikLocale.en =>
          'The backend archives the prior context, attempts, and progress instead of deleting them; in-progress work may be abandoned.',
        ModrikLocale.fr =>
          'Le serveur archive l’ancien contexte, les tentatives et la progression au lieu de les supprimer ; un travail en cours peut être abandonné.',
      };
  String get syncWarning => switch (locale) {
        ModrikLocale.ar =>
          'يجب مزامنة الإجابات والتغييرات المعلّقة قبل إعادة الضبط.',
        ModrikLocale.en =>
          'Pending answers and changes must be synchronized before reset.',
        ModrikLocale.fr =>
          'Les réponses et changements en attente doivent être synchronisés avant la réinitialisation.',
      };
  String get confirmConsequences => switch (locale) {
        ModrikLocale.ar => 'أفهم نتائج الأرشفة وإعادة الضبط.',
        ModrikLocale.en => 'I understand the archival reset consequences.',
        ModrikLocale.fr =>
          'Je comprends les conséquences de l’archivage et de la réinitialisation.',
      };
  String get loading => switch (locale) {
        ModrikLocale.ar => 'جارٍ تحميل المسارات الأكاديمية المصرح بها.',
        ModrikLocale.en => 'Loading authorized academic tracks.',
        ModrikLocale.fr => 'Chargement des parcours académiques autorisés.',
      };
  String get empty => switch (locale) {
        ModrikLocale.ar => 'لا توجد مسارات أكاديمية مصرح بها حاليًا.',
        ModrikLocale.en => 'No academic tracks are currently authorized.',
        ModrikLocale.fr =>
          'Aucun parcours académique n’est actuellement autorisé.',
      };
  String get offline => switch (locale) {
        ModrikLocale.ar =>
          'يلزم الاتصال لجلب قائمة المسارات المعتمدة أو تغيير السياق.',
        ModrikLocale.en =>
          'Reconnect to load the authorized catalogue or change academic context.',
        ModrikLocale.fr =>
          'Reconnectez-vous pour charger le catalogue autorisé ou changer de contexte.',
      };
  String get permission => switch (locale) {
        ModrikLocale.ar => 'لا تسمح الجلسة الحالية بقراءة قائمة المسارات.',
        ModrikLocale.en =>
          'The current session cannot read the academic-track catalogue.',
        ModrikLocale.fr =>
          'La session actuelle ne peut pas lire le catalogue des parcours.',
      };
  String get error => switch (locale) {
        ModrikLocale.ar => 'تعذر تحميل قائمة المسارات. حاول مرة أخرى.',
        ModrikLocale.en =>
          'The academic-track catalogue could not be loaded. Retry.',
        ModrikLocale.fr =>
          'Le catalogue des parcours n’a pas pu être chargé. Réessayez.',
      };
  String get transitionFailed => switch (locale) {
        ModrikLocale.ar =>
          'لم يطبق الخادم الانتقال الأكاديمي. راجع الحالة وأعد المحاولة.',
        ModrikLocale.en =>
          'The backend did not apply the academic transition. Review the state and retry.',
        ModrikLocale.fr =>
          'Le serveur n’a pas appliqué la transition académique. Vérifiez l’état et réessayez.',
      };
  String get retry => switch (locale) {
        ModrikLocale.ar => 'إعادة المحاولة',
        ModrikLocale.en => 'Retry',
        ModrikLocale.fr => 'Réessayer',
      };
  String get cancel => switch (locale) {
        ModrikLocale.ar => 'إلغاء',
        ModrikLocale.en => 'Cancel',
        ModrikLocale.fr => 'Annuler',
      };
  String get confirm => switch (locale) {
        ModrikLocale.ar => 'تأكيد إعادة الضبط',
        ModrikLocale.en => 'Confirm reset',
        ModrikLocale.fr => 'Confirmer la réinitialisation',
      };
}

extension _FirstOrNull<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
