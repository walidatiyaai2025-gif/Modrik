import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';

void main() {
  testWidgets('renders the MODRIK bootstrap shell', (tester) async {
    await tester.pumpWidget(const ModrikApp());

    expect(find.text('MODRIK | مُدرك'), findsOneWidget);
    expect(find.text('Mobile bootstrap shell'), findsOneWidget);
  });
}
