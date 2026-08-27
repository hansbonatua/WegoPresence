import { Camera, Check, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    open: boolean;
    title: string;
    description: string;
    onClose: () => void;
    onPhotoCaptured: (photo: File) => void;
};

type CameraStatus = 'starting' | 'live' | 'error';

function cameraErrorMessage(error: unknown): string {
    if (error instanceof DOMException && error.name === 'NotAllowedError') {
        return 'Camera permission is required to take attendance.';
    }

    return 'Camera is not available on this device.';
}

export default function AttendanceCameraDialog({
    open,
    title,
    description,
    onClose,
    onPhotoCaptured,
}: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const photoBlobRef = useRef<Blob | null>(null);
    const photoUrlRef = useRef<string | null>(null);
    const [status, setStatus] = useState<CameraStatus>('starting');
    const [photoUrl, setPhotoUrl] = useState<string | null>(null);

    const stopStream = useCallback(() => {
        if (streamRef.current) {
            streamRef.current.getTracks().forEach((track) => track.stop());
            streamRef.current = null;
        }

        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }
    }, []);

    const revokePhotoUrl = useCallback(() => {
        if (photoUrlRef.current) {
            URL.revokeObjectURL(photoUrlRef.current);
            photoUrlRef.current = null;
        }

        setPhotoUrl(null);
    }, []);

    const startCamera = useCallback(async () => {
        setStatus('starting');
        revokePhotoUrl();
        stopStream();

        if (!navigator.mediaDevices?.getUserMedia) {
            const message = 'Camera is not available on this device.';
            setStatus('error');
            toast.error(message);

            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' },
            });

            streamRef.current = stream;
            console.log('[attendance-camera] getUserMedia succeeded', {
                videoTracks: stream.getVideoTracks().length,
                trackReadyState: stream.getVideoTracks()[0]?.readyState,
                trackSettings: stream.getVideoTracks()[0]?.getSettings(),
            });
            setStatus('live');
        } catch (error) {
            const message = cameraErrorMessage(error);
            stopStream();
            setStatus('error');
            toast.error(message);
        }
    }, [revokePhotoUrl, stopStream]);

    const videoCallbackRef = useCallback(
        (el: HTMLVideoElement | null) => {
            videoRef.current = el;

            if (el && streamRef.current) {
                console.log(
                    '[attendance-camera] videoCallbackRef: attaching stream to element',
                    el,
                );
                el.srcObject = streamRef.current;

                el.play().catch(() => {
                    /* autoplay policy – silent */
                });
            }
        },
        [],
    );

    const handleVideoLoadedMetadata = useCallback(() => {
        const video = videoRef.current;
        const stream = streamRef.current;

        console.log('[attendance-camera] onLoadedMetadata', {
            hasVideo: !!video,
            videoReadyState: video?.readyState,
            videoWidth: video?.videoWidth,
            videoHeight: video?.videoHeight,
            hasStream: !!stream,
            videoTracks: stream?.getVideoTracks().length,
            trackReadyState: stream?.getVideoTracks()[0]?.readyState,
            trackSettings: stream?.getVideoTracks()[0]?.getSettings(),
            srcObjectSet: !!video?.srcObject,
        });

        if (video && stream && video.srcObject !== stream) {
            video.srcObject = stream;
        }

        video?.play().catch(() => {
            /* autoplay policy – silent */
        });
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        const timer = window.setTimeout(() => {
            void startCamera();
        }, 0);

        return () => {
            window.clearTimeout(timer);
            videoRef.current = null;
            stopStream();
            photoBlobRef.current = null;
            revokePhotoUrl();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function takePhoto() {
        const video = videoRef.current;

        if (!video || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const context = canvas.getContext('2d');

        if (!context) {
            return;
        }

        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    return;
                }

                revokePhotoUrl();
                photoBlobRef.current = blob;
                const url = URL.createObjectURL(blob);
                photoUrlRef.current = url;
                setPhotoUrl(url);
                stopStream();
            },
            'image/jpeg',
            0.85,
        );
    }

    function retake() {
        revokePhotoUrl();
        photoBlobRef.current = null;
        void startCamera();
    }

    function usePhoto() {
        const blob = photoBlobRef.current;

        if (!blob) {
            return;
        }

        photoBlobRef.current = null;
        revokePhotoUrl();

        onPhotoCaptured(
            new File([blob], 'attendance-photo.jpg', {
                type: 'image/jpeg',
            }),
        );
    }

    function handleClose() {
        stopStream();
        photoBlobRef.current = null;
        revokePhotoUrl();
        onClose();
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    handleClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <div className="relative overflow-hidden rounded-lg bg-black">
                    {status === 'starting' && (
                        <div className="flex aspect-video w-full items-center justify-center gap-2 text-white">
                            <Spinner />
                            Starting camera...
                        </div>
                    )}

                    {status === 'error' && (
                        <div className="flex aspect-video w-full flex-col items-center justify-center gap-3 px-6 text-center text-white">
                            <p className="text-sm">
                                Camera is not available on this device.
                            </p>
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => void startCamera()}
                            >
                                Try again
                            </Button>
                        </div>
                    )}

                    {status === 'live' && photoUrl === null && (
                        <video
                            ref={videoCallbackRef}
                            autoPlay
                            playsInline
                            muted
                            className="aspect-video w-full"
                            onLoadedMetadata={handleVideoLoadedMetadata}
                        />
                    )}

                    {photoUrl !== null && (
                        <img
                            src={photoUrl}
                            alt="Captured attendance photo"
                            className="aspect-video w-full object-cover"
                        />
                    )}
                </div>

                <div className="flex justify-center gap-2">
                    {status === 'live' && photoUrl === null && (
                        <Button onClick={takePhoto}>
                            <Camera />
                            Take Photo
                        </Button>
                    )}

                    {photoUrl !== null && (
                        <>
                            <Button variant="secondary" onClick={retake}>
                                <RefreshCw />
                                Retake
                            </Button>
                            <Button onClick={usePhoto}>
                                <Check />
                                Use Photo
                            </Button>
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
