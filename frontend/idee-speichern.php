<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">

    <title>Geschenkideeee speichern</title>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <h1>Geschenkidee speichern</h1>

    <form class="ideen-formular">

        <label for="person">Person:</label>
        <select id="person" name="person">
            <option value="">Person auswählen</option>
        </select>

        <label for="text">Idee / Beschreibung:</label>
        <textarea id="text" name="text"></textarea>

        <label for="link">Link:</label>
        <input type="url" id="link" name="link">

        <label for="bild">Bild:</label>
        <input type="file" id="bild" name="bild" accept="image/*">

        <button type="submit">Idee speichern</button>

    </form>

</body>

</html>