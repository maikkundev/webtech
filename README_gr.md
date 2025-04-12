# Project Τεχνολογιές Διαδικτύου

[English](README.md) | [Ελληνικά](README_gr.md)

## Πώς να τρέξετε

1. Κατεβάστε και εγκαταστήστε το Docker Desktop

    > Windows: Απαιτείται το WSL για να λειτουργεί το Docker. [Οδηγίες εδώ...](https://learn.microsoft.com/en-us/windows/wsl/install#change-the-default-linux-distribution-installed)

2. Κλωνοποιήστε το project

    ```bash
    git clone https://github.com/maikkundev/webtech.git
    ```

3. Πλοηγηθείτε στο project μέσω του τερματικού σας

    ```bash
    cd ~/projects/webtech # Παράδειγμα
    ```

4. Τρέξτε μέσω Docker

    ```bash
    docker compose up --build -d # -d: λειτουργεί σε αποσύνδεση
    ```

5. Επισκεφθείτε την εφαρμογή στο `localhost:80`

## Πώς να συνεισφέρετε

1. Δημιουργήστε ένα νέο branch από το `main`

    ```bash
    git checkout main # Βεβαιωθείτε ότι βρίσκεστε στο κύριο branch
    git pull # Βεβαιωθείτε ότι έχετε τις τελευταίες αλλαγές

    git checkout -b feature/YOUR_NAME/your-feature-name # Για νέα λειτουργία. Παράδειγμα: feature/maikkundev/awesome-feature. -b: δημιουργεί ένα νέο branch

    # ΕΝΑΛΛΑΚΤΙΚΑ
    
    git checkout -b bugfix/YOUR_NAME/your-bug-name # Για ενημερώσεις σφαλμάτων. Παράδειγμα: bugfix/maikkundev/fix-issue-123. -b: δημιουργεί ένα νέο branch
    ```

2. Κάντε commit τις αλλαγές σας

    ```bash
    git add . # Προσθέστε όλες τις αλλαγές στην προσωρινή 
    git commit -m "Your commit message" # Κάντε commit με ένα μήνυμα. Παράδειγμα: "Added support for light/dark mode"
    ```

    > Σημείωση: Χρησιμοποιήστε την εντολή `git status` για να ελέγξετε την κατάσταση του branch σας και να δείτε ποια αρχεία έχουν αλλάξει.

    > Σημείωση: Τα commit μηνύματα πρέπει να είναι στα ***Αγγλικά***.

3. Μετά την δημιουργία και το commit των αλλαγών σας, ανεβάστε το branch στο GitHub

    ```bash
    git push origin [BRANCH_NAME] # Παράδειγμα: git push origin feature/maikkundev/new-feature
    ```

4. Δημιουργήστε ένα pull request από το branch σας προς το `main`

    1. Μεταβείτε στο [Pull Requests](https://github.com/maikkundev/webtech/pulls)
    2. Κάντε κλικ στο `New Pull Request`
    3. Επιλέξτε το branch σας από το αναδυόμενο μενού
    4. Προσθέστε έναν τίτλο και περιγράψτε τις αλλαγές σας
    5. Κάντε κλικ στο `Create Pull Request`
    > Σημείωση: Μπορείτε να κάνετε επιπλέον αλλαγές στο branch σας και μετά τη δημιουργία του pull request. Το pull request θα ενημερώνεται αυτόματα με τις νέες σας αλλαγές.
