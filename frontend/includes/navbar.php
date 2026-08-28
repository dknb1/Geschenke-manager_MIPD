<nav class="navbar">
    <div class="navbar-logo">
        <span class="logo-icon">GM</span>
        <span class="logo-text">Geschenke-Manager</span>
    </div>

    <div class="navbar-right">

        <button class="glocke" popovertarget="benachrichtigungen">
            🔔
        </button>

        <a href="index.php" class="home-button">Home</a>

    </div>
</nav>


<div id="benachrichtigungen" popover class="benachrichtigungs-fenster">

    <div class="fenster-kopf">
        <h2>Benachrichtigungen</h2>

        <button class="schliessen"
                popovertarget="benachrichtigungen"
                popovertargetaction="hide">
            ×
        </button>
    </div>

    <p>In 10 Tagen ist Geburtstag von Max.</p>

    <p>In 18 Tagen ist Hochzeit von Anna.</p>

</div>