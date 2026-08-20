import 'package:flutter/material.dart';
import 'package:modrik_design_tokens/modrik_design_tokens.dart';

void main() {
  runApp(const ModrikApp());
}

class ModrikApp extends StatelessWidget {
  const ModrikApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'MODRIK | مُدرك',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: ModrikColors.teal,
          primary: ModrikColors.teal,
          secondary: ModrikColors.blue,
          surface: ModrikColors.white,
        ),
        scaffoldBackgroundColor: ModrikColors.background,
        fontFamily: ModrikTypography.latinFamily,
        useMaterial3: true,
      ),
      home: const BootstrapShell(),
    );
  }
}

class BootstrapShell extends StatelessWidget {
  const BootstrapShell({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(28),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'MODRIK | مُدرك',
                        style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                          color: ModrikColors.navy,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Mobile bootstrap shell',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: ModrikColors.blue,
                        ),
                      ),
                      const SizedBox(height: 10),
                      const Text(
                        'Authentication and learning workflows begin only after the shared contracts are green.',
                        style: TextStyle(color: ModrikColors.slate, height: 1.5),
                      ),
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
}
