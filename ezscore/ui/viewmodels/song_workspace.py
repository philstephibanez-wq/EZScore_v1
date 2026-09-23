from __future__ import annotations

from ezscore.core.permissions import permissions_for
from ezscore.mock.songs import MOCK_SONG
from ezscore.mock.stems import MOCK_STEMS
from ezscore.mock.timeline import MOCK_MEASURES, MOCK_WORDS


def build(user):
    return {
        "song": dict(MOCK_SONG),
        "stems": list(MOCK_STEMS),
        "measures": list(MOCK_MEASURES),
        "words": list(MOCK_WORDS),
        "permissions": permissions_for(user),
    }
