# 🧹 PULIZIA COMPLETA - Istruzioni

## 📁 FILE DA ELIMINARE VIA FTP

Vai nella cartella `/wp-content/plugins/FP-Multilanguage/` ed **ELIMINA questi file**:

```
❌ fp-multilanguage-minimal.php
❌ fp-multilanguage-base.php
❌ fp-multilanguage-core.php
❌ fp-multilanguage-wrapper.php
❌ fp-multilanguage-wrapper-safe.php
❌ fp-multilanguage-constructor.php
```

**LASCIA SOLO:**
```
✅ fp-multilanguage.php (il file principale)
```

---

## 🔄 DOPO LA PULIZIA

1. **Ricarica** la pagina Plugin in WordPress (Ctrl+F5)
2. Dovresti vedere **UN SOLO** "FP Multilanguage v0.4.1"
3. **Attiva** il plugin
4. **DIMMI:** Si attiva? Errore 500?

---

## ⚡ ALTERNATIVA VELOCE

Se hai accesso SSH/FTP, puoi fare così:

### Via FTP:
```
1. Elimina TUTTA la cartella: /wp-content/plugins/FP-Multilanguage/
2. Carica FP-Multilanguage-COSTRUTTORE-VUOTO.zip
3. Estrai
4. Verifica che esista SOLO fp-multilanguage.php (nessun file *-minimal, *-base, ecc.)
5. Vai su Plugin WordPress
6. Attiva "FP Multilanguage"
```

---

**ELIMINA I FILE TEST E RIPROVA!** 🧹

