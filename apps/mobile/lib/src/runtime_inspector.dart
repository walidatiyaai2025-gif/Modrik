import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:modrik_design_tokens/modrik_design_tokens.dart';

import 'runtime_diagnostics.dart';

class RuntimeInspectorSnapshot {
  const RuntimeInspectorSnapshot({
    required this.locale,
    required this.direction,
    required this.connectivity,
    required this.currentFlow,
    required this.pendingSyncCount,
    required this.cacheItemCount,
  });

  final String locale;
  final TextDirection direction;
  final DiagnosticConnectivity connectivity;
  final String currentFlow;
  final int pendingSyncCount;
  final int cacheItemCount;
}

class RuntimeInspectorHost extends StatelessWidget {
  const RuntimeInspectorHost({
    super.key,
    required this.diagnostics,
    required this.snapshot,
    required this.child,
  });

  final RuntimeDiagnostics diagnostics;
  final RuntimeInspectorSnapshot snapshot;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (!diagnostics.enabled) return child;
    final copy = RuntimeInspectorCopy(snapshot.locale);
    return Stack(
      children: [
        child,
        PositionedDirectional(
          end: 12,
          bottom: 76,
          child: SafeArea(
            minimum: const EdgeInsets.all(4),
            child: Semantics(
              button: true,
              label: copy.t('open_inspector'),
              child: Tooltip(
                message: copy.t('open_inspector'),
                child: Material(
                  elevation: 4,
                  color: ModrikColors.navy,
                  shape: const CircleBorder(),
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => RuntimeInspectorScreen(
                          diagnostics: diagnostics,
                          snapshot: snapshot,
                        ),
                      ),
                    ),
                    child: const SizedBox(
                      width: 52,
                      height: 52,
                      child: Icon(
                        Icons.monitor_heart_outlined,
                        color: ModrikColors.white,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class RuntimeInspectorScreen extends StatefulWidget {
  const RuntimeInspectorScreen({
    super.key,
    required this.diagnostics,
    required this.snapshot,
  });

  final RuntimeDiagnostics diagnostics;
  final RuntimeInspectorSnapshot snapshot;

  @override
  State<RuntimeInspectorScreen> createState() => _RuntimeInspectorScreenState();
}

class _RuntimeInspectorScreenState extends State<RuntimeInspectorScreen> {
  String _severity = 'all';
  String _category = '';
  String _correlation = '';

  @override
  Widget build(BuildContext context) {
    final snapshot = widget.snapshot;
    final copy = RuntimeInspectorCopy(snapshot.locale);
    return Directionality(
      textDirection: snapshot.direction,
      child: Scaffold(
        appBar: AppBar(
          title: Text(copy.t('title')),
        ),
        body: SafeArea(
          child: AnimatedBuilder(
            animation: widget.diagnostics,
            builder: (context, _) {
              final events = _filteredEvents(widget.diagnostics.events);
              return ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  _RuntimeSummaryCard(
                    diagnostics: widget.diagnostics,
                    snapshot: snapshot,
                    copy: copy,
                  ),
                  const SizedBox(height: 16),
                  _ActionBar(
                    copy: copy,
                    onCopy: () => _copyBundle(copy),
                    onExport: () => _exportBundle(copy),
                    onClear: () => _confirmClear(copy),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    copy.t('filters'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: ModrikColors.navy,
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: _severity,
                    decoration: InputDecoration(
                      labelText: copy.t('severity'),
                      border: const OutlineInputBorder(),
                    ),
                    items: [
                      DropdownMenuItem(value: 'all', child: Text(copy.t('all'))),
                      ...DiagnosticSeverity.values.map(
                        (severity) => DropdownMenuItem(
                          value: severity.name,
                          child: Text(severity.name.toUpperCase()),
                        ),
                      ),
                    ],
                    onChanged: (value) => setState(() => _severity = value ?? 'all'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    decoration: InputDecoration(
                      labelText: copy.t('category'),
                      border: const OutlineInputBorder(),
                    ),
                    onChanged: (value) => setState(() => _category = value.trim()),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    decoration: InputDecoration(
                      labelText: copy.t('correlation'),
                      hintText: 'mcr-… / server reference',
                      border: const OutlineInputBorder(),
                    ),
                    onChanged: (value) => setState(() => _correlation = value.trim()),
                  ),
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          copy.t('timeline'),
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                color: ModrikColors.navy,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                      ),
                      Text('${events.length}'),
                    ],
                  ),
                  const SizedBox(height: 8),
                  if (events.isEmpty)
                    _EmptyTimeline(copy: copy)
                  else
                    ...events.reversed.map(
                      (event) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _DiagnosticEventCard(event: event, copy: copy),
                      ),
                    ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  List<RuntimeDiagnosticEvent> _filteredEvents(List<RuntimeDiagnosticEvent> events) {
    final categoryNeedle = _category.toLowerCase();
    final correlationNeedle = _correlation.toLowerCase();
    return events.where((event) {
      if (_severity != 'all' && event.severity.name != _severity) return false;
      if (categoryNeedle.isNotEmpty &&
          !event.category.toLowerCase().contains(categoryNeedle)) {
        return false;
      }
      if (correlationNeedle.isNotEmpty &&
          !event.correlationId.toLowerCase().contains(correlationNeedle)) {
        return false;
      }
      return true;
    }).toList(growable: false);
  }

  String _bundle() {
    final snapshot = widget.snapshot;
    return widget.diagnostics.exportSanitizedJson(
      locale: snapshot.locale,
      direction: snapshot.direction == TextDirection.rtl ? 'rtl' : 'ltr',
      connectivity: snapshot.connectivity,
      currentFlow: snapshot.currentFlow,
      pendingSyncCount: snapshot.pendingSyncCount,
      cacheItemCount: snapshot.cacheItemCount,
    );
  }

  Future<void> _copyBundle(RuntimeInspectorCopy copy) async {
    await Clipboard.setData(ClipboardData(text: _bundle()));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(copy.t('copied'))),
    );
  }

  Future<void> _exportBundle(RuntimeInspectorCopy copy) async {
    final snapshot = widget.snapshot;
    final path = await widget.diagnostics.writeSanitizedExport(
      locale: snapshot.locale,
      direction: snapshot.direction == TextDirection.rtl ? 'rtl' : 'ltr',
      connectivity: snapshot.connectivity,
      currentFlow: snapshot.currentFlow,
      pendingSyncCount: snapshot.pendingSyncCount,
      cacheItemCount: snapshot.cacheItemCount,
    );
    if (!mounted) return;
    final filename = path == null ? null : File(path).uri.pathSegments.last;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          filename == null
              ? copy.t('export_failed')
              : '${copy.t('exported')}: $filename',
        ),
      ),
    );
  }

  Future<void> _confirmClear(RuntimeInspectorCopy copy) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(copy.t('clear_title')),
        content: Text(copy.t('clear_body')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(copy.t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(copy.t('clear')),
          ),
        ],
      ),
    );
    if (confirmed == true) await widget.diagnostics.clear();
  }
}

class _RuntimeSummaryCard extends StatelessWidget {
  const _RuntimeSummaryCard({
    required this.diagnostics,
    required this.snapshot,
    required this.copy,
  });

  final RuntimeDiagnostics diagnostics;
  final RuntimeInspectorSnapshot snapshot;
  final RuntimeInspectorCopy copy;

  @override
  Widget build(BuildContext context) {
    final metadata = diagnostics.metadata;
    final lastError = diagnostics.lastError;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              copy.t('runtime_summary'),
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: ModrikColors.navy,
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 12),
            _SummaryRow(label: copy.t('environment'), value: metadata.environment),
            _SummaryRow(label: copy.t('version'), value: '${metadata.version}+${metadata.build}'),
            _SummaryRow(label: copy.t('commit'), value: metadata.commit),
            _SummaryRow(label: copy.t('platform'), value: '${metadata.platform} · ${metadata.runtime}'),
            _SummaryRow(label: copy.t('locale'), value: snapshot.locale.toUpperCase()),
            _SummaryRow(label: copy.t('connectivity'), value: snapshot.connectivity.name),
            _SummaryRow(label: copy.t('flow'), value: snapshot.currentFlow),
            _SummaryRow(label: copy.t('pending_sync'), value: '${snapshot.pendingSyncCount}'),
            _SummaryRow(label: copy.t('cache_items'), value: '${snapshot.cacheItemCount}'),
            _SummaryRow(
              label: copy.t('last_support_reference'),
              value: lastError?.correlationId ?? copy.t('none'),
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 2,
            child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
          ),
          const SizedBox(width: 12),
          Expanded(
            flex: 3,
            child: SelectableText(value),
          ),
        ],
      ),
    );
  }
}

class _ActionBar extends StatelessWidget {
  const _ActionBar({
    required this.copy,
    required this.onCopy,
    required this.onExport,
    required this.onClear,
  });

  final RuntimeInspectorCopy copy;
  final VoidCallback onCopy;
  final VoidCallback onExport;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        _InspectorAction(
          icon: Icons.copy_outlined,
          label: copy.t('copy'),
          onPressed: onCopy,
        ),
        _InspectorAction(
          icon: Icons.file_download_outlined,
          label: copy.t('export'),
          onPressed: onExport,
        ),
        _InspectorAction(
          icon: Icons.delete_outline,
          label: copy.t('clear'),
          onPressed: onClear,
        ),
      ],
    );
  }
}

class _InspectorAction extends StatelessWidget {
  const _InspectorAction({
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 48),
      child: OutlinedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon),
        label: Text(label),
      ),
    );
  }
}

class _DiagnosticEventCard extends StatelessWidget {
  const _DiagnosticEventCard({required this.event, required this.copy});

  final RuntimeDiagnosticEvent event;
  final RuntimeInspectorCopy copy;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: '${event.severity.name}, ${event.category}, ${event.result}',
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Wrap(
                spacing: 8,
                runSpacing: 6,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  Chip(label: Text(event.severity.name.toUpperCase())),
                  Chip(label: Text(event.category)),
                  Text(
                    event.timestampUtc.toIso8601String(),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                event.operation,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              Text('${copy.t('result')}: ${event.result}'),
              if (event.stableCode != null)
                Text('${copy.t('code')}: ${event.stableCode}'),
              Text('${copy.t('correlation')}: ${event.correlationId}'),
              Text('${copy.t('connectivity')}: ${event.connectivity.name}'),
              if (event.fingerprint != null)
                Text('${copy.t('fingerprint')}: ${event.fingerprint}'),
              if (event.metadata.isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(
                  event.metadata.entries
                      .map((entry) => '${entry.key}=${entry.value}')
                      .join(' · '),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _EmptyTimeline extends StatelessWidget {
  const _EmptyTimeline({required this.copy});

  final RuntimeInspectorCopy copy;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 32),
        child: Center(child: Text(copy.t('empty'))),
      ),
    );
  }
}

class RuntimeInspectorCopy {
  RuntimeInspectorCopy(String locale)
      : locale = const {'ar', 'en', 'fr'}.contains(locale) ? locale : 'en';

  final String locale;

  String t(String key) => _copy[locale]?[key] ?? _copy['en']?[key] ?? key;
}

const Map<String, Map<String, String>> _copy = {
  'en': {
    'title': 'Runtime Inspector',
    'open_inspector': 'Open runtime diagnostics',
    'runtime_summary': 'Runtime summary',
    'environment': 'Environment',
    'version': 'Version / build',
    'commit': 'Commit',
    'platform': 'Platform / runtime',
    'locale': 'Locale',
    'connectivity': 'Connectivity',
    'flow': 'Current flow',
    'pending_sync': 'Pending sync operations',
    'cache_items': 'Cached learning items',
    'last_support_reference': 'Last support reference',
    'none': 'None',
    'filters': 'Timeline filters',
    'severity': 'Severity',
    'all': 'All',
    'category': 'Category contains',
    'correlation': 'Correlation ID',
    'timeline': 'Recent diagnostic timeline',
    'result': 'Result',
    'code': 'Stable code',
    'fingerprint': 'Fingerprint',
    'copy': 'Copy JSON',
    'export': 'Export JSON',
    'clear': 'Clear diagnostics',
    'copied': 'Sanitized diagnostic JSON copied.',
    'exported': 'Sanitized diagnostic file created',
    'export_failed': 'Diagnostic export could not be created.',
    'clear_title': 'Clear local diagnostics?',
    'clear_body': 'This removes the bounded local diagnostic timeline only. Learning data is not changed.',
    'cancel': 'Cancel',
    'empty': 'No diagnostic events match these filters.',
  },
  'ar': {
    'title': 'فاحص التشغيل',
    'open_inspector': 'فتح تشخيصات التشغيل',
    'runtime_summary': 'ملخص التشغيل',
    'environment': 'البيئة',
    'version': 'الإصدار / البناء',
    'commit': 'نسخة الكود',
    'platform': 'المنصة / بيئة التشغيل',
    'locale': 'اللغة',
    'connectivity': 'الاتصال',
    'flow': 'المسار الحالي',
    'pending_sync': 'عمليات المزامنة المعلقة',
    'cache_items': 'عناصر التعلم المخزنة',
    'last_support_reference': 'آخر مرجع للدعم',
    'none': 'لا يوجد',
    'filters': 'مرشحات السجل',
    'severity': 'الخطورة',
    'all': 'الكل',
    'category': 'التصنيف يحتوي على',
    'correlation': 'معرّف الارتباط',
    'timeline': 'سجل التشخيصات الأخيرة',
    'result': 'النتيجة',
    'code': 'الرمز الثابت',
    'fingerprint': 'البصمة',
    'copy': 'نسخ JSON',
    'export': 'تصدير JSON',
    'clear': 'مسح التشخيصات',
    'copied': 'تم نسخ JSON التشخيصي المنقح.',
    'exported': 'تم إنشاء ملف التشخيص المنقح',
    'export_failed': 'تعذر إنشاء ملف التشخيص.',
    'clear_title': 'مسح التشخيصات المحلية؟',
    'clear_body': 'سيؤدي ذلك إلى حذف سجل التشخيص المحلي المحدود فقط، ولن يغيّر بيانات التعلم.',
    'cancel': 'إلغاء',
    'empty': 'لا توجد أحداث تشخيص تطابق المرشحات.',
  },
  'fr': {
    'title': 'Inspecteur d’exécution',
    'open_inspector': 'Ouvrir les diagnostics d’exécution',
    'runtime_summary': 'Résumé d’exécution',
    'environment': 'Environnement',
    'version': 'Version / build',
    'commit': 'Commit',
    'platform': 'Plateforme / exécution',
    'locale': 'Langue',
    'connectivity': 'Connectivité',
    'flow': 'Parcours actuel',
    'pending_sync': 'Opérations de synchro en attente',
    'cache_items': 'Éléments d’apprentissage en cache',
    'last_support_reference': 'Dernière référence support',
    'none': 'Aucune',
    'filters': 'Filtres de la chronologie',
    'severity': 'Sévérité',
    'all': 'Toutes',
    'category': 'Catégorie contient',
    'correlation': 'ID de corrélation',
    'timeline': 'Chronologie récente des diagnostics',
    'result': 'Résultat',
    'code': 'Code stable',
    'fingerprint': 'Empreinte',
    'copy': 'Copier le JSON',
    'export': 'Exporter le JSON',
    'clear': 'Effacer les diagnostics',
    'copied': 'JSON de diagnostic assaini copié.',
    'exported': 'Fichier de diagnostic assaini créé',
    'export_failed': 'Impossible de créer l’export de diagnostic.',
    'clear_title': 'Effacer les diagnostics locaux ?',
    'clear_body': 'Cela supprime uniquement la chronologie locale limitée. Les données d’apprentissage ne changent pas.',
    'cancel': 'Annuler',
    'empty': 'Aucun événement de diagnostic ne correspond aux filtres.',
  },
};
