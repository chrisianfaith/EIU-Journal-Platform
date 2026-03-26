/**
 * EIU Research Publication — Nav User Menu v1.8
 *
 * Standalone vanilla JS. No jQuery. No eiuRP dependency.
 * Loads sitewide only for logged-in researchers/reviewers.
 * Data is injected via wp_localize_script as eiuNavUser.
 *
 * v1.8: Robust login-item hiding — hides ALL login/sign-in menu
 * items BEFORE injecting the user widget to prevent simultaneous
 * display of both "Login" and "Hi, Christian".
 */
/* global eiuNavUser */
(function () {
    'use strict';

    /* Guard: only run when PHP injected the user data */
    if ( typeof eiuNavUser === 'undefined' ) { return; }

    var data = eiuNavUser;

    /* Run after DOM is ready */
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    function init() {
        /* ── Step A: Hide login items FIRST (before showing widget) ── */
        hideLoginItems();

        var widget = document.getElementById( 'eiu-nav-user-widget' );
        if ( ! widget ) { return; }

        /* ── Step B: Populate dynamic content ────────────────────── */
        var avatar   = widget.querySelector( '.eiu-nav-avatar' );
        var greeting = widget.querySelector( '.eiu-nav-greeting' );

        if ( avatar ) {
            avatar.src = data.avatarUrl || '';
            avatar.alt = data.firstName || data.name || '';
            avatar.onerror = function () {
                /* Graceful fallback: initials circle */
                this.style.display = 'none';
                var fallback = document.createElement( 'span' );
                fallback.className = 'eiu-nav-av-initial';
                fallback.textContent = ( data.firstName || data.name || 'U' ).charAt(0).toUpperCase();
                this.parentNode.insertBefore( fallback, this );
            };
        }

        if ( greeting ) {
            var firstName = ( data.firstName || data.name || '' ).split( ' ' )[0];
            greeting.innerHTML =
                'Hi, ' + escHtml( firstName ) +
                ' <span class="eiu-nav-role-badge">' + escHtml( data.role ) + '</span>';
        }

        /* ── Step C: Set dropdown link hrefs ─────────────────────── */
        var items = widget.querySelectorAll( '[data-eiu-href]' );
        for ( var k = 0; k < items.length; k++ ) {
            var key  = items[k].getAttribute( 'data-eiu-href' );
            var href = key === 'dashboard' ? data.dashboardUrl
                     : key === 'profile'   ? data.profileUrl
                     : key === 'logout'    ? data.logoutUrl
                     : '#';
            items[k].setAttribute( 'href', href );
        }

        /* ── Step D: Show widget and inject into nav ──────────────── */
        widget.style.display = '';
        injectIntoNav( widget );

        /* ── Step E: Dropdown toggle ──────────────────────────────── */
        var trigger  = widget.querySelector( '.eiu-nav-trigger' );
        var dropdown = widget.querySelector( '.eiu-nav-dropdown' );
        if ( ! trigger || ! dropdown ) { return; }

        function openMenu() {
            dropdown.classList.add( 'is-open' );
            trigger.setAttribute( 'aria-expanded', 'true' );
        }
        function closeMenu() {
            dropdown.classList.remove( 'is-open' );
            trigger.setAttribute( 'aria-expanded', 'false' );
        }

        trigger.addEventListener( 'click', function ( e ) {
            e.stopPropagation();
            dropdown.classList.contains( 'is-open' ) ? closeMenu() : openMenu();
        } );
        trigger.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Enter' || e.key === ' ' ) {
                e.preventDefault();
                dropdown.classList.contains( 'is-open' ) ? closeMenu() : openMenu();
            }
            if ( e.key === 'Escape' ) { closeMenu(); }
        } );
        document.addEventListener( 'click', function ( e ) {
            if ( ! widget.contains( e.target ) ) { closeMenu(); }
        } );
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) { closeMenu(); }
        } );
    }

    /* ═══════════════════════════════════════════════════════════
       hideLoginItems()
       ───────────────────────────────────────────────────────────
       Scans the entire nav for any anchor / menu-item that looks
       like a Login or Sign-in link and hides its parent <li>.

       Matching strategy (applied in order, stops hiding a given
       element once any strategy matches it):

       1. Exact-URL match against loginPageUrls (from PHP).
       2. Href fragment match: href contains "login", "wp-login",
          "sign-in", "/researcher/", "/reviewer/".
       3. Link-text match: visible text is one of the login phrases.
       4. CSS class match on the <li>: contains "login", "account",
          "my-account".
       5. Already-hidden by server-side filter (class already set) —
          nothing to do, but ensure display:none is applied.
    ═══════════════════════════════════════════════════════════ */
    function hideLoginItems() {
        /* Login page URLs provided by PHP (most reliable) */
        var loginUrls = ( data.loginPageUrls || [] ).map( function ( u ) {
            return normalizeUrl( u );
        } );

        /* Text phrases that indicate a login link */
        var loginTexts = [
            'login', 'log in', 'sign in', 'signin',
            'researcher login', 'reviewer login',
            'account', 'register', 'create account',
        ];

        /* href fragments that indicate a login link */
        var loginHrefFrags = [
            'wp-login', '/login', 'sign-in', '/researcher', '/reviewer',
        ];

        /* All candidate <li> elements across the whole document */
        var allLi = document.querySelectorAll(
            'header li, nav li, .nav-menu li, .menu li, ' +
            '#primary-menu li, #main-menu li, .navbar-nav li, ' +
            '.elementor-nav-menu li, .e-n-menu li'
        );

        for ( var i = 0; i < allLi.length; i++ ) {
            var li = allLi[i];

            /* Skip items already hidden by the server-side filter */
            if ( li.classList.contains( 'eiu-rp-login-hidden' ) ) {
                li.style.display = 'none';
                continue;
            }

            /* Gather anchors inside this <li> */
            var anchors = li.querySelectorAll( 'a' );
            var matched = false;

            for ( var j = 0; j < anchors.length; j++ ) {
                var a        = anchors[j];
                var href     = ( a.getAttribute( 'href' ) || '' );
                var normHref = normalizeUrl( href );
                var text     = ( a.textContent || a.innerText || '' ).trim().toLowerCase();

                /* Strategy 1 — exact URL match */
                for ( var u = 0; u < loginUrls.length; u++ ) {
                    if ( loginUrls[u] && normHref === loginUrls[u] ) {
                        matched = true; break;
                    }
                }
                if ( matched ) { break; }

                /* Strategy 2 — href fragment */
                for ( var f = 0; f < loginHrefFrags.length; f++ ) {
                    if ( href.indexOf( loginHrefFrags[f] ) !== -1 ) {
                        matched = true; break;
                    }
                }
                if ( matched ) { break; }

                /* Strategy 3 — visible link text */
                for ( var t = 0; t < loginTexts.length; t++ ) {
                    if ( text === loginTexts[t] ) {
                        matched = true; break;
                    }
                }
                if ( matched ) { break; }
            }

            /* Strategy 4 — <li> class name */
            if ( ! matched ) {
                var cls = ( li.getAttribute( 'class' ) || '' ).toLowerCase();
                if ( /\b(login|my-account|account|sign-in|signin)\b/.test( cls ) ) {
                    matched = true;
                }
            }

            if ( matched ) {
                li.classList.add( 'eiu-rp-login-hidden' );
                li.style.display = 'none';
            }
        }

        /* Also hide standalone <a> tags (not inside <li>) that are login links.
           Common in Elementor button widgets and custom header builders. */
        var standaloneLinks = document.querySelectorAll(
            'header a, .elementor-widget-button a, .header-button a'
        );
        for ( var m = 0; m < standaloneLinks.length; m++ ) {
            var sa       = standaloneLinks[m];
            var saHref   = sa.getAttribute( 'href' ) || '';
            var saText   = ( sa.textContent || sa.innerText || '' ).trim().toLowerCase();
            var saMatch  = false;

            /* Exact URL */
            var saNorm = normalizeUrl( saHref );
            for ( var lu = 0; lu < loginUrls.length; lu++ ) {
                if ( loginUrls[lu] && saNorm === loginUrls[lu] ) {
                    saMatch = true; break;
                }
            }

            /* Href fragment */
            if ( !saMatch ) {
                for ( var lf = 0; lf < loginHrefFrags.length; lf++ ) {
                    if ( saHref.indexOf( loginHrefFrags[lf] ) !== -1 ) {
                        saMatch = true; break;
                    }
                }
            }

            /* Text */
            if ( !saMatch && loginTexts.indexOf( saText ) !== -1 ) {
                saMatch = true;
            }

            if ( saMatch ) {
                /* Hide the closest visually-distinct ancestor
                   (widget wrapper, button, or the link itself) */
                var hideTgt = sa.closest( '.elementor-widget, .wp-block-button, .header-btn-wrap' ) || sa;
                hideTgt.style.display = 'none';
                hideTgt.classList.add( 'eiu-rp-login-hidden' );
            }
        }
    }

    /* ── Helpers ──────────────────────────────────────────────── */

    /* Normalize a URL for comparison: lowercase, strip trailing slash and
       query string, so  "https://site.com/researcher/" === "https://site.com/researcher"  */
    function normalizeUrl( url ) {
        if ( ! url ) { return ''; }
        try {
            var u = new URL( url, window.location.href );
            return ( u.origin + u.pathname ).toLowerCase().replace( /\/$/, '' );
        } catch (e) {
            return url.toLowerCase().replace( /\/$/, '' );
        }
    }

    function escHtml( str ) {
        return String( str || '' )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#39;' );
    }

    /*
     * injectIntoNav — four-step theme-agnostic strategy.
     *
     * After hideLoginItems() has already removed all login entries,
     * this simply appends the user widget to the nav.
     *
     * Step 1: Replace any remaining login/account menu item
     *         (safety net — should already be hidden).
     * Step 2: Append to the primary nav <ul>.
     * Step 3: Append to the first <nav> found.
     * Step 4: Fixed floating badge (last resort).
     */
    function injectIntoNav( el ) {
        /* Wrap in <li> for insertion into <ul> menus */
        var li = document.createElement( 'li' );
        li.id = 'eiu-nav-user-li';
        li.style.cssText = 'list-style:none;display:flex;align-items:center;margin:0;padding:0;';
        li.appendChild( el );

        /* Step 1 — Replace any surviving login item (safety net) */
        var loginSelectors = [
            'li.menu-item-type-custom a[href*="login"]',
            'li.menu-item-type-custom a[href*="wp-login"]',
            '.nav-item a[href*="login"]',
            '.menu-item a[href*="login"]',
            'li[class*="my-account"]',
        ];
        for ( var s = 0; s < loginSelectors.length; s++ ) {
            var match = document.querySelector( loginSelectors[s] );
            if ( match ) {
                var parentLi = match.closest ? match.closest( 'li' ) : match.parentNode;
                if ( parentLi && parentLi.parentNode && !parentLi.id ) {
                    parentLi.parentNode.replaceChild( li, parentLi );
                    return;
                }
            }
        }

        /* Step 2 — Append to primary nav <ul> */
        var primaryNav = document.querySelector(
            '#primary-navigation ul, #main-navigation ul, #site-navigation ul, ' +
            'nav.main-navigation ul, nav#main-menu ul, .primary-menu, ' +
            'nav ul.nav-menu, header nav ul, .navbar-nav, nav ul:first-child'
        );
        if ( primaryNav ) {
            primaryNav.appendChild( li );
            return;
        }

        /* Step 3 — First <nav> element */
        var firstNav = document.querySelector( 'header nav, nav' );
        if ( firstNav ) {
            var ul = firstNav.querySelector( 'ul' );
            if ( ul ) { ul.appendChild( li ); return; }
            firstNav.appendChild( el );
            return;
        }

        /* Step 4 — Fixed floating badge */
        el.style.cssText = 'position:fixed;top:12px;right:16px;z-index:99999;display:inline-flex;align-items:center;';
        document.body.appendChild( el );
    }

}());

