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
  bool _transitionBusy = false;

  MobileLearningController get controller => widget.controller;
  _CatalogueCopy get copy => _CatalogueCopy(controller.locale);

  @override
  void initState() {
    super.initState();
    scheduleMicrotask(_loadCatalogue);
  }

  @override
  void didUpdateWidget(covariant AcademicContextResetBoundary oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.controller, widget.controller)) {
      _operationKey = null;
      scheduleMicrotask(_loadCatalogue);
    }
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

    setState(() => _state = _CatalogueState.loading);
    try {
      final tracks = await gateway.academicTracks();
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
    if (controller.academicContext?.requiresOnboarding ?? false) {
      return _buildOnboarding(context);
    }

    final currentTrack = controller.academicContext?.academicTrackId;
    final hasAlternative = _state == _CatalogueState.ready &&
        _tracks.any((track) => track.id != currentTrack);
    if (!hasAlternative) return widget.child;

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
                constraints: const BoxConstraints(maxWidth: 520),
                child: Semantics(
                  button: true,
                  label: copy.change,
                  child: FilledButton.icon(
                    onPressed: controller.isBusy || _transitionBusy
                        ? null
                        : () => _showResetDialog(context),
                    icon: const Icon(Icons.swap_horiz_outlined),
                    label: Text(copy.change, textAlign: TextAlign.center),
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
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
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(copy.onboardingBody),
                      const SizedBox(height: 20),
                      _catalogueBody(context, isReset: false),
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

  Widget _catalogueBody(BuildContext context, {required bool isReset}) {
    switch (_state) {
      case _CatalogueState.loading:
        return Semantics(
          liveRegion: true,
          child: const Center(child: CircularProgressIndicator()),
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
        final currentTrackId = controller.academicContext?.academicTrackId;
        final effectiveSelection = _selectedTrackId ?? _tracks.first.id;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<String>(
              key: ValueKey('academic-track-$isReset'),
              value: effectiveSelection,
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
                      });
                    },
            ),
            if (isReset) ...[
              const SizedBox(height: 16),
              Text(copy.resetBody),
              const SizedBox(height: 8),
              Text(copy.syncWarning),
            ],
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _transitionBusy ||
                      controller.isBusy ||
                      (isReset && effectiveSelection == currentTrackId)
                  ? null
                  : () => _applySelection(isReset: isReset),
              child: _transitionBusy
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(isReset ? copy.confirm : copy.activate),
            ),
          ],
        );
    }
  }

  Future<void> _showResetDialog(BuildContext context) async {
    _selectedTrackId = controller.academicContext?.academicTrackId ??
        _tracks.firstOrNull?.id;
    _operationKey = null;
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, refreshDialog) {
          return AlertDialog(
            scrollable: true,
            title: Text(copy.resetTitle),
            content: _catalogueBody(context, isReset: true),
            actions: [
              TextButton(
                onPressed: _transitionBusy
                    ? null
                    : () => Navigator.of(dialogContext).pop(),
                child: Text(copy.cancel),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _applySelection({required bool isReset}) async {
    final selected = _selectedTrackId;
    if (selected == null) return;
    setState(() => _transitionBusy = true);
    final key = _operationKey ??= newLogicalCommandKey();
    if (isReset) {
      await controller.resetAcademicContext(selected, idempotencyKey: key);
    } else {
      await controller.activateAcademicContext(selected, idempotencyKey: key);
    }
    if (!mounted) return;
    final succeeded = controller.academicContext?.academicTrackId == selected &&
        controller.academicContext?.state == 'active';
    setState(() {
      _transitionBusy = false;
      if (succeeded) _operationKey = null;
    });
    if (succeeded) {
      await _loadCatalogue();
      if (isReset && mounted && Navigator.of(context).canPop()) {
        Navigator.of(context).pop();
      }
    }
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
        ModrikLocale.en => 'Only tracks authorized by the backend for this learner are shown.',
        ModrikLocale.fr => 'Seuls les parcours autorisés par le serveur pour cet élève sont affichés.',
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
        ModrikLocale.ar => 'سيؤرشف الخادم السياق السابق والمحاولات والتقدّم بدل حذفها، وقد تُنهى المحاولة الجارية.',
        ModrikLocale.en => 'The backend archives the prior context, attempts, and progress instead of deleting them; in-progress work may be abandoned.',
        ModrikLocale.fr => 'Le serveur archive l’ancien contexte, les tentatives et la progression au lieu de les supprimer ; un travail en cours peut être abandonné.',
      };
  String get syncWarning => switch (locale) {
        ModrikLocale.ar => 'يجب مزامنة الإجابات والتغييرات المعلّقة قبل إعادة الضبط.',
        ModrikLocale.en => 'Pending answers and changes must be synchronized before reset.',
        ModrikLocale.fr => 'Les réponses et changements en attente doivent être synchronisés avant la réinitialisation.',
      };
  String get empty => switch (locale) {
        ModrikLocale.ar => 'لا توجد مسارات أكاديمية مصرح بها حاليًا.',
        ModrikLocale.en => 'No academic tracks are currently authorized.',
        ModrikLocale.fr => 'Aucun parcours académique n’est actuellement autorisé.',
      };
  String get offline => switch (locale) {
        ModrikLocale.ar => 'يلزم الاتصال لجلب قائمة المسارات المعتمدة أو تغيير السياق.',
        ModrikLocale.en => 'Reconnect to load the authorized catalogue or change academic context.',
        ModrikLocale.fr => 'Reconnectez-vous pour charger le catalogue autorisé ou changer de contexte.',
      };
  String get permission => switch (locale) {
        ModrikLocale.ar => 'لا تسمح الجلسة الحالية بقراءة قائمة المسارات.',
        ModrikLocale.en => 'The current session cannot read the academic-track catalogue.',
        ModrikLocale.fr => 'La session actuelle ne peut pas lire le catalogue des parcours.',
      };
  String get error => switch (locale) {
        ModrikLocale.ar => 'تعذر تحميل قائمة المسارات. حاول مرة أخرى.',
        ModrikLocale.en => 'The academic-track catalogue could not be loaded. Retry.',
        ModrikLocale.fr => 'Le catalogue des parcours n’a pas pu être chargé. Réessayez.',
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
