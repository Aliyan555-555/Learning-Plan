// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Learning Plan — gamified map interactions AMD module.
 *
 * This is the single source of behaviour for the plan map, the admin action
 * menus and the badges modal. There is no inline JavaScript and no second
 * (non-AMD) copy of this file.
 *
 * @module     local_learningplan/map
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var LPMap = {
        activeNode: null,
        resizeObserver: null,
        hamburgerBound: false,
        modalBound: false,

        init: function() {
            var relayout = function() {
                LPMap.layoutIslandTheme();
                LPMap.drawPath();
            };

            relayout();
            this.bindNodes();
            this.bindPopupClose();
            this.bindHamburgerMenu();
            this.bindBadgesModal();

            var container = document.getElementById('lp-path-container');
            if (window.ResizeObserver && container) {
                this.resizeObserver = new ResizeObserver(this.debounce(relayout, 60));
                this.resizeObserver.observe(container);
            }
            window.addEventListener('resize', this.debounce(relayout, 120));
            window.addEventListener('orientationchange', this.debounce(relayout, 200));

            setTimeout(relayout, 80);
            setTimeout(relayout, 350);
        },

        /**
         * Image-based "Island" theme: precisely anchors each island graphic and its step
         * node(s) using real rendered geometry, so nodes always land on the artwork's land
         * area and islands scale responsively with the container instead of a fixed px size.
         */
        layoutIslandTheme: function() {
            var map = document.getElementById('lp-map');
            if (!map || !map.classList.contains('lp-theme-island')) {
                return;
            }
            var container = document.getElementById('lp-path-container');
            if (!container) {
                return;
            }
            var islands = Array.prototype.slice.call(container.querySelectorAll('.lp-island-art'));
            if (!islands.length) {
                return;
            }
            var containerWidth = container.getBoundingClientRect().width;
            if (!containerWidth) {
                return;
            }

            islands.sort(function(a, b) {
                return parseInt(a.dataset.group, 10) - parseInt(b.dataset.group, 10);
            });

            // Alternating horizontal anchors so consecutive islands zig-zag like the
            // reference map instead of stacking in a straight line.
            var anchors = [50, 30, 72, 38, 64, 26, 76, 46];
            var gap = Math.max(36, containerWidth * 0.05);
            var y = 36;

            islands.forEach(function(islandEl) {
                var group = parseInt(islandEl.dataset.group, 10);
                var widthPct = parseFloat(islandEl.dataset.width);
                var aspect = parseFloat(islandEl.dataset.aspect);
                var slots = [];
                try {
                    slots = JSON.parse(islandEl.dataset.slots || '[]');
                } catch (e) {
                    slots = [];
                }

                var banner = container.querySelector('.lp-chapter-banner[data-beforegroup="' + group + '"]');
                if (banner) {
                    banner.style.top = Math.round(y) + 'px';
                    var bh = banner.getBoundingClientRect().height || 60;
                    y += bh + 28;
                }

                var widthPx = containerWidth * (widthPct / 100);
                var heightPx = widthPx * aspect;
                // Nodes have a fixed CSS footprint (~80-120px); shrink them in step with a
                // small island so two slots on the same graphic never visually overlap.
                var nodeScale = Math.max(0.42, Math.min(1, widthPx / 300));
                var anchorPct = anchors[group % anchors.length];
                var centerX = containerWidth * (anchorPct / 100);
                var leftPx = centerX - widthPx / 2;
                leftPx = Math.max(-widthPx * 0.12, Math.min(containerWidth - widthPx * 0.88, leftPx));

                islandEl.style.left = leftPx + 'px';
                islandEl.style.top = Math.round(y) + 'px';
                islandEl.style.width = widthPx + 'px';
                islandEl.style.transform = 'none';
                islandEl.classList.add('lp-island-ready');

                slots.forEach(function(slot, i) {
                    var node = container.querySelector(
                        '.lp-node[data-groupindex="' + group + '"][data-slotindex="' + i + '"]'
                    );
                    if (!node) {
                        return;
                    }
                    var nx = leftPx + (slot.x / 100) * widthPx;
                    var ny = y + (slot.y / 100) * heightPx;
                    node.style.left = Math.round(nx) + 'px';
                    node.style.top = Math.round(ny) + 'px';
                    node.style.setProperty('--lp-node-scale', nodeScale.toFixed(3));
                    node.classList.add('lp-island-ready');
                });

                y += heightPx + gap;
            });

            var finish = container.querySelector('.lp-finish-node');
            if (finish) {
                finish.style.top = Math.round(y) + 'px';
                y += (finish.getBoundingClientRect().height || 80) + gap;
            }

            container.style.height = Math.round(y) + 'px';
        },

        strings: function() {
            return (window.M && M.local_learningplan && M.local_learningplan.strings) ? M.local_learningplan.strings : {
                open: 'Open', markasdone: 'Mark as done', locked: 'Locked',
                lockedhint: 'Complete the previous step to unlock this one.',
                selfreported: 'Self-reported', points: 'points', completed: 'Completed'
            };
        },

        debounce: function(fn, wait) {
            var t;
            return function() {
                clearTimeout(t);
                var args = arguments;
                var ctx = this;
                t = setTimeout(function() { fn.apply(ctx, args); }, wait);
            };
        },

        drawPath: function() {
            var container = document.getElementById('lp-path-container');
            var svg = document.getElementById('lp-path-svg');
            if (!container || !svg) {
                return;
            }

            var rings = Array.prototype.slice.call(container.querySelectorAll('.lp-node-ring'));
            var finish = container.querySelector('.lp-finish-node');
            var targets = rings.slice();

            var crect = container.getBoundingClientRect();
            if (crect.width === 0 || crect.height === 0) {
                return;
            }

            var points = targets.map(function(node) {
                var r = node.getBoundingClientRect();
                return {
                    x: (r.left + r.width / 2) - crect.left,
                    y: (r.top + r.height / 2) - crect.top
                };
            });

            if (finish) {
                var fr = finish.getBoundingClientRect();
                points.push({
                    x: (fr.left + fr.width / 2) - crect.left,
                    y: (fr.top + fr.height / 2) - crect.top
                });
            }

            if (points.length < 2) {
                svg.innerHTML = '';
                return;
            }

            var completedCount = container.querySelectorAll('.lp-node-completed').length;
            var totalSegments = points.length - 1;
            var fillRatio = totalSegments > 0 ? Math.max(0, Math.min(1, completedCount / totalSegments)) : 0;

            var d = this.catmullRomPath(points);

            svg.setAttribute('width', crect.width);
            svg.setAttribute('height', crect.height);
            svg.setAttribute('viewBox', '0 0 ' + crect.width + ' ' + crect.height);

            svg.innerHTML =
                '<path d="' + d + '" class="lp-path-base-shadow"></path>' +
                '<path d="' + d + '" class="lp-path-base"></path>' +
                '<path d="' + d + '" class="lp-path-fill" pathLength="1" ' +
                'style="stroke-dashoffset:' + (1 - fillRatio) + '"></path>';
        },

        catmullRomPath: function(pts) {
            if (pts.length < 2) {
                return '';
            }
            var d = 'M ' + pts[0].x.toFixed(1) + ' ' + pts[0].y.toFixed(1) + ' ';
            for (var i = 0; i < pts.length - 1; i++) {
                var p0 = pts[i === 0 ? 0 : i - 1];
                var p1 = pts[i];
                var p2 = pts[i + 1];
                var p3 = pts[i + 2 < pts.length ? i + 2 : i + 1];

                var cp1x = p1.x + (p2.x - p0.x) / 6;
                var cp1y = p1.y + (p2.y - p0.y) / 6;
                var cp2x = p2.x - (p3.x - p1.x) / 6;
                var cp2y = p2.y - (p3.y - p1.y) / 6;

                d += 'C ' + cp1x.toFixed(1) + ' ' + cp1y.toFixed(1) + ', ' +
                     cp2x.toFixed(1) + ' ' + cp2y.toFixed(1) + ', ' +
                     p2.x.toFixed(1) + ' ' + p2.y.toFixed(1) + ' ';
            }
            return d;
        },

        bindNodes: function() {
            var nodes = document.querySelectorAll('.lp-node');
            nodes.forEach(function(node) {
                if (node.dataset.lpBound === '1') {
                    return;
                }
                node.dataset.lpBound = '1';
                node.addEventListener('click', function(e) {
                    e.stopPropagation();
                    LPMap.onNodeActivate(node);
                });
                node.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        LPMap.onNodeActivate(node);
                    }
                });
            });
        },

        onNodeActivate: function(node) {
            if (node.dataset.status === 'locked') {
                node.classList.remove('lp-shake');
                void node.offsetWidth;
                node.classList.add('lp-shake');
                this.showPopup(node, true);
                return;
            }
            this.showPopup(node, false);
        },

        showPopup: function(node, locked) {
            var popup = document.getElementById('lp-popup');
            if (!popup) {
                return;
            }
            var s = this.strings();
            this.activeNode = node;

            var typeEl = document.getElementById('lp-popup-type');
            var titleEl = document.getElementById('lp-popup-title');
            var metaEl = document.getElementById('lp-popup-meta');
            var starsEl = document.getElementById('lp-popup-stars');
            var actionsEl = document.getElementById('lp-popup-actions');

            var typeText = node.dataset.type || '';
            if (node.dataset.stepnumber) {
                typeText = 'Step ' + node.dataset.stepnumber + ' • ' + typeText;
            }
            typeEl.textContent = typeText;
            titleEl.textContent = node.dataset.title || '';

            var pointsChip = document.createElement('span');
            pointsChip.className = 'lp-chip lp-chip-gold';
            pointsChip.textContent = '+' + node.dataset.points + ' ' + s.points;
            metaEl.innerHTML = '';
            metaEl.appendChild(pointsChip);
            if (node.dataset.status === 'completed') {
                var doneChip = document.createElement('span');
                doneChip.className = 'lp-chip';
                doneChip.textContent = '✓ ' + s.completed;
                metaEl.appendChild(doneChip);
            }

            if (node.dataset.status === 'completed') {
                var earned = parseInt(node.dataset.stars, 10) || 0;
                var max = parseInt(node.dataset.maxstars, 10) || 3;
                var starsHtml = '';
                for (var i = 1; i <= max; i++) {
                    starsHtml += '<span class="lp-star' + (i <= earned ? ' filled' : '') + '">★</span>';
                }
                starsEl.innerHTML = starsHtml;
                starsEl.style.display = '';
            } else {
                starsEl.innerHTML = '';
                starsEl.style.display = 'none';
            }

            actionsEl.innerHTML = '';

            if (locked) {
                if (node.dataset.prereqgap === '1') {
                    var prereqNum = node.dataset.prereqnum || '1';
                    var prereqTitle = node.dataset.prereqtitle || '';
                    var prereqStepId = node.dataset.prereqstepid || '';

                    var alertBox = document.createElement('div');
                    alertBox.className = 'lp-popup-prereq-alert';

                    var alertHeader = document.createElement('div');
                    alertHeader.className = 'lp-popup-prereq-header';
                    alertHeader.innerHTML = '<span class="lp-popup-prereq-icon">⏳</span> <strong>' + (s.prereq_gap_title || 'Prerequisite Steps Required') + '</strong>';

                    var alertBody = document.createElement('div');
                    alertBody.className = 'lp-popup-prereq-body';
                    var msgText = 'You have already completed this course/activity in Moodle! However, to progress on this sequential path, you must first complete earlier steps: <strong>Step ' + prereqNum + (prereqTitle ? ': ' + prereqTitle : '') + '</strong>.';
                    alertBody.innerHTML = msgText;

                    var alertFoot = document.createElement('div');
                    alertFoot.className = 'lp-popup-prereq-footer';
                    alertFoot.textContent = s.prereq_gap_autonotice || 'Once you finish the preceding step(s), this step will unlock and complete automatically without needing to redo it.';

                    alertBox.appendChild(alertHeader);
                    alertBox.appendChild(alertBody);
                    alertBox.appendChild(alertFoot);
                    actionsEl.appendChild(alertBox);

                    // Add "Go to Step X" quick focus button
                    if (prereqStepId) {
                        var goToPrereqBtn = document.createElement('button');
                        goToPrereqBtn.type = 'button';
                        goToPrereqBtn.className = 'lp-btn lp-btn-primary lp-btn-gotoprereq';
                        var gotoLabel = s.gotoprereq ? s.gotoprereq.replace('{$a}', prereqNum) : ('Go to Step ' + prereqNum);
                        goToPrereqBtn.innerHTML = '👉 ' + gotoLabel;
                        goToPrereqBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            popup.hidden = true;
                            var targetNode = document.querySelector('.lp-node[data-stepid="' + prereqStepId + '"]');
                            if (targetNode) {
                                targetNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                setTimeout(function() {
                                    LPMap.onNodeActivate(targetNode);
                                }, 350);
                            }
                        });
                        actionsEl.appendChild(goToPrereqBtn);
                    }

                    // Also provide secondary option to open the course if desired
                    actionsEl.appendChild(this.buildOpenLink(node, s, 'lp-btn-secondary'));
                } else {
                    var hint = document.createElement('div');
                    hint.className = 'lp-popup-note';
                    hint.textContent = s.lockedhint;
                    actionsEl.appendChild(hint);
                }
            } else if (node.dataset.status === 'completed') {
                if (node.dataset.verifiedby === 'self') {
                    var note = document.createElement('div');
                    note.className = 'lp-popup-note';
                    note.textContent = s.selfreported;
                    actionsEl.appendChild(note);
                }
                actionsEl.appendChild(this.buildOpenLink(node, s, 'lp-btn-secondary'));
            } else {
                actionsEl.appendChild(this.buildOpenLink(node, s, 'lp-btn-primary'));

                if (node.dataset.selfreport === '1') {
                    var doneBtn = document.createElement('button');
                    doneBtn.type = 'button';
                    doneBtn.className = 'lp-btn lp-btn-secondary';
                    doneBtn.textContent = s.markasdone;
                    doneBtn.addEventListener('click', function() {
                        LPMap.markDone(node.dataset.stepid, doneBtn);
                    });
                    actionsEl.appendChild(doneBtn);
                }
            }

            if (node.dataset.stepediturl) {
                var editStepBtn = document.createElement('a');
                editStepBtn.className = 'lp-btn lp-btn-secondary';
                editStepBtn.href = node.dataset.stepediturl;
                editStepBtn.textContent = '✏️ ' + (s.editstep || 'Edit Step');
                actionsEl.appendChild(editStepBtn);
            }

            this.positionPopup(popup, node);
            popup.hidden = false;
        },

        /**
         * Build the "Open" link for a node, refusing any href that is not a safe
         * http(s)/relative URL so a tampered step URL can never become a
         * javascript: sink.
         */
        buildOpenLink: function(node, s, extraClass) {
            var link = document.createElement('a');
            link.className = 'lp-btn ' + extraClass;
            link.textContent = s.open;
            var href = node.dataset.href || '#';
            if (/^(https?:)?\/\//i.test(href) || href.charAt(0) === '/' || href.charAt(0) === '#') {
                link.href = href;
            } else {
                link.href = '#';
            }
            link.target = node.dataset.target || '_self';
            link.rel = 'noopener noreferrer';
            return link;
        },

        positionPopup: function(popup, node) {
            var container = document.getElementById('lp-path-container');
            var crect = container.getBoundingClientRect();
            var nrect = node.getBoundingClientRect();

            popup.style.display = 'block';
            var popupWidth = popup.offsetWidth || 270;
            var popupHeight = popup.offsetHeight || 180;

            var left = (nrect.left + nrect.width / 2) - crect.left - (popupWidth / 2);
            left = Math.max(12, Math.min(left, crect.width - popupWidth - 12));

            var top = (nrect.top - crect.top) - popupHeight - 16;
            var flip = false;
            if (top < 10) {
                top = (nrect.bottom - crect.top) + 16;
                flip = true;
            }

            popup.style.left = left + 'px';
            popup.style.top = top + 'px';
            popup.classList.toggle('lp-popup-flip', flip);
        },

        bindPopupClose: function() {
            var closeBtn = document.getElementById('lp-popup-close');
            var popup = document.getElementById('lp-popup');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    popup.hidden = true;
                });
            }
            document.addEventListener('click', function(e) {
                if (popup && !popup.hidden && !popup.contains(e.target) && !e.target.closest('.lp-node')) {
                    popup.hidden = true;
                }
            });
        },

        closeAllMenus: function() {
            var openDropdowns = document.querySelectorAll('.lp-hamburger-dropdown:not([hidden]), .lp-hamburger-dropdown.is-open');
            for (var i = 0; i < openDropdowns.length; i++) {
                openDropdowns[i].setAttribute('hidden', 'hidden');
                openDropdowns[i].classList.remove('is-open');
                var wrap = openDropdowns[i].closest('.lp-hamburger-wrap');
                if (wrap) {
                    wrap.classList.remove('menu-open');
                    var b = wrap.querySelector('.lp-hamburger-btn');
                    if (b) {
                        b.classList.remove('active');
                        b.setAttribute('aria-expanded', 'false');
                    }
                }
                var card = openDropdowns[i].closest('.lp-plan-card');
                if (card) {
                    card.classList.remove('lp-card-menu-open');
                }
            }
        },

        bindHamburgerMenu: function() {
            if (this.hamburgerBound) {
                return;
            }
            this.hamburgerBound = true;
            var self = this;

            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.lp-hamburger-btn');
                if (btn) {
                    e.preventDefault();
                    e.stopPropagation();
                    var wrap = btn.closest('.lp-hamburger-wrap');
                    if (!wrap) { return; }
                    var dropdown = wrap.querySelector('.lp-hamburger-dropdown');
                    if (!dropdown) { return; }

                    var wasHidden = dropdown.hasAttribute('hidden') || !dropdown.classList.contains('is-open');
                    self.closeAllMenus();

                    if (wasHidden) {
                        dropdown.removeAttribute('hidden');
                        dropdown.classList.add('is-open');
                        wrap.classList.add('menu-open');
                        btn.classList.add('active');
                        btn.setAttribute('aria-expanded', 'true');
                        var parentCard = btn.closest('.lp-plan-card');
                        if (parentCard) {
                            parentCard.classList.add('lp-card-menu-open');
                        }
                    }
                    return;
                }

                if (!e.target.closest('.lp-hamburger-dropdown')) {
                    self.closeAllMenus();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeAllMenus();
                }
            });
        },

        bindBadgesModal: function() {
            if (this.modalBound) {
                return;
            }
            this.modalBound = true;

            var backdrop = document.getElementById('lpBadgesModalBackdrop');
            if (!backdrop) {
                return;
            }

            var open = function(e) {
                if (e) { e.preventDefault(); }
                backdrop.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };
            var close = function(e) {
                if (e) { e.preventDefault(); }
                backdrop.style.display = 'none';
                document.body.style.overflow = '';
            };

            document.addEventListener('click', function(e) {
                if (e.target.closest('.lp-btn-open-badges-modal') || e.target.closest('#lpOpenBadgesModalBtn')) {
                    open(e);
                } else if (e.target.closest('.lp-badges-modal-close')) {
                    close(e);
                } else if (e.target === backdrop) {
                    close(e);
                }
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    close();
                }
            });
        },

        markDone: function(stepid, btn) {
            btn.disabled = true;
            var original = btn.textContent;
            btn.textContent = '...';

            var params = new URLSearchParams();
            params.set('action', 'markdone');
            params.set('stepid', stepid);
            params.set('sesskey', (window.M && M.local_learningplan) ? M.local_learningplan.sesskey : '');

            var url = (window.M && M.local_learningplan) ? M.local_learningplan.ajaxurl : 'ajax.php';

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params.toString()
            })
                .then(function(r) { return r.json(); })
                .then(function(json) {
                    if (json && json.success) {
                        LPMap.celebrate();
                        setTimeout(function() { window.location.reload(); }, 700);
                    } else {
                        btn.disabled = false;
                        btn.textContent = original;
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.textContent = original;
                });
        },

        celebrate: function() {
            var colors = ['#0284c7', '#38bdf8', '#f59e0b', '#ea580c', '#16a34a', '#ec4899', '#a855f7'];
            for (var i = 0; i < 48; i++) {
                var piece = document.createElement('div');
                piece.className = 'lp-confetti-piece';
                piece.style.left = (Math.random() * 100) + 'vw';
                piece.style.background = colors[Math.floor(Math.random() * colors.length)];
                piece.style.animationDuration = (1.5 + Math.random() * 1.5) + 's';
                piece.style.opacity = (0.7 + Math.random() * 0.3).toFixed(2);
                document.body.appendChild(piece);
                (function(el) {
                    setTimeout(function() { el.remove(); }, 3200);
                })(piece);
            }
        }
    };

    window.LPMap = LPMap;

    return {
        init: function() {
            LPMap.init();
        },
        LPMap: LPMap
    };
});
