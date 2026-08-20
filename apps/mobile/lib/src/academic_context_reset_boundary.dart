import 'package:flutter/material.dart';

import 'mobile_learning_controller.dart';
import 'models.dart';

class AcademicContextResetBoundary extends StatelessWidget {
  const AcademicContextResetBoundary({
    super.key,
    required this.controller,
    required this.child,
  });

  final MobileLearningController controller;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final targetTrack = controller.config.academicTrackId;
    final currentTrack = controller.academicContext?.academicTrackId;
    final canOfferReset =
        controller.academicContext?.state == 'active' &&
        targetTrack != null &&
        currentTrack != null &&
        targetTrack != currentTrack;

    if (!canOfferReset) return child;

    final copy = _ResetCopy(controller.locale);
    return Stack(
      children: [
        child,
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
                    onPressed: controller.isBusy
                        ? null
                        : () => _confirmReset(context, copy),
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

  Future<void> _confirmReset(BuildContext context, _ResetCopy copy) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        scrollable: true,
        title: Text(copy.title),
        content: Text(copy.body),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(copy.cancel),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(copy.confirm),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await controller.resetConfiguredAcademicContext();
    }
  }
}

class _ResetCopy {
  const _ResetCopy(this.locale);

  final ModrikLocale locale;

  String get change => switch (locale) {
        ModrikLocale.ar => 'تغيير السياق الأكاديمي',
        ModrikLocale.en => 'Change academic context',
        ModrikLocale.fr => 'Changer le contexte académique',
      };

  String get title => switch (locale) {
        ModrikLocale.ar => 'تأكيد تغيير السياق',
        ModrikLocale.en => 'Confirm context change',
        ModrikLocale.fr => 'Confirmer le changement',
      };

  String get body => switch (locale) {
        ModrikLocale.ar =>
          'الخادم وحده يقرر الانتقال الأكاديمي ويحفظ المحاولات والتقدّم السابقين في الأرشيف. يجب مزامنة أي إجابات معلّقة أولًا.',
        ModrikLocale.en =>
          'Only the backend decides the academic transition and archives prior attempts and progress. Any pending answers must be synchronized first.',
        ModrikLocale.fr =>
          'Seul le serveur décide de la transition académique et archive les tentatives et la progression précédentes. Les réponses en attente doivent d’abord être synchronisées.',
      };

  String get cancel => switch (locale) {
        ModrikLocale.ar => 'إلغاء',
        ModrikLocale.en => 'Cancel',
        ModrikLocale.fr => 'Annuler',
      };

  String get confirm => switch (locale) {
        ModrikLocale.ar => 'تأكيد',
        ModrikLocale.en => 'Confirm',
        ModrikLocale.fr => 'Confirmer',
      };
}
