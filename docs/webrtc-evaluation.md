# WebRTC Evaluation

WebRTC is not in the launch path. Evaluate it after the PJSIP call path,
VectaVoIP trunk provisioning, DID routing, rating, and payment flows are stable.

Before adding browser calling:

- confirm Asterisk 20 or 22 PJSIP works for normal SIP calls
- define TLS certificate ownership for WSS
- decide whether Asterisk terminates WebRTC directly or sits behind an SBC
- document ICE/STUN/TURN requirements
- test codec policy, DTMF, caller ID, recording, and CDR behavior
- confirm toll-fraud controls work for browser-originated calls
